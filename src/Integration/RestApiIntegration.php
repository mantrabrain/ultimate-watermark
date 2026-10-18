<?php

namespace MantraBrain\UltimateWatermark\Integration;

use MantraBrain\UltimateWatermark\Utils\AttachmentGuard;
use MantraBrain\UltimateWatermark\Utils\WatermarkHelper;

/**
 * REST API Integration
 * 
 * Handles watermark application for images uploaded via REST API (page/post editor)
 * This runs regardless of is_admin() status since REST API requests don't have is_admin() = true
 */
class RestApiIntegration
{
    /**
     * Instance registered by init(), so add-ons (Pro) can reuse the pipeline.
     */
    private static ?self $instance = null;

    /**
     * Attachments waiting to be evaluated once their parent post is fully saved.
     * Map of attachment ID => parent post ID.
     *
     * @var array<int, int>
     */
    private array $pendingQueue = [];

    /**
     * Attachments currently being processed in this request (re-entrancy lock).
     *
     * @var array<int, bool>
     */
    private array $processing = [];

    /**
     * Whether the queue is being flushed right now.
     */
    private bool $flushing = false;

    /**
     * Return the active instance (null before the plugin booted).
     */
    public static function instance(): ?self
    {
        return self::$instance;
    }

    /**
     * Initialize the REST API integration
     */
    public function init(): void
    {
        self::$instance = $this;

        // Flag downloadable-product uploads so they are never watermarked.
        AttachmentGuard::init();

        // Deleting an image (from anywhere: admin, REST, WP-CLI, vendor
        // dashboards) removes its backups and usage records.
        add_action('delete_attachment', [$this, 'handleAttachmentDeleted']);

        // Hook into add_attachment (fires for ALL attachment creation, including REST API)
        add_action('add_attachment', [$this, 'markForWatermarking']);

        // CRITICAL: Capture watermark toggle state BEFORE the raw request body is consumed
        // by WordPress REST API file upload handler. This ensures we can detect the toggle
        // state even when the raw input has been consumed by the time other hooks fire.
        add_filter('rest_request_before_callbacks', [$this, 'captureWatermarkToggleFromRequest'], 1, 3);

        // Hook into REST API attachment creation (for page/post editor uploads)
        add_action('rest_insert_attachment', [$this, 'handleRestApiAttachmentUpload'], 10, 3);
        add_action('rest_after_insert_attachment', [$this, 'handleRestApiAttachmentUpload'], 10, 3);

        // Featured image / product gallery / variation image assignments.
        // `added_post_meta` matters: on a brand-new product (WooCommerce CRUD,
        // Dokan frontend dashboard, REST) `_thumbnail_id` does not exist yet, so
        // WordPress fires `added_post_meta`, never `updated_post_meta`.
        add_action('added_post_meta', [$this, 'handleImageMetaChange'], 20, 4);
        add_action('updated_post_meta', [$this, 'handleImageMetaChange'], 20, 4);

        // Re-evaluate a post's images when the taxonomy terms that rules can
        // test (product category/tag, post category) change. Dokan and
        // WooCommerce assign categories AFTER the featured image is stored.
        add_action('set_object_terms', [$this, 'handleObjectTermsChange'], 20, 6);

        // A post leaving auto-draft is saved for the first time: queue its images.
        add_action('transition_post_status', [$this, 'handlePostStatusTransition'], 20, 3);

        // Evaluate queued images once the parent is fully saved: right after
        // Dokan finishes a product save, and at the end of every request.
        add_action('dokan_new_product_added', [$this, 'flushPendingQueue'], 999);
        add_action('dokan_product_updated', [$this, 'flushPendingQueue'], 999);
        add_action('shutdown', [$this, 'flushPendingQueue'], 0);

        // Hook into wp_insert_post for attachments as ultimate fallback
        // This fires for ALL post creation including REST API
        add_action('wp_insert_post', [$this, 'handleAttachmentPostInsert'], 10, 3);

        // Hook into metadata generation to apply watermarks after thumbnails are created
        add_filter('wp_generate_attachment_metadata', [$this, 'processAfterMetadataGeneration'], 10, 2);
    }

    /**
     * Queue images when a featured image, WooCommerce gallery or variation image is assigned.
     *
     * @param int    $meta_id    Meta ID.
     * @param int    $post_id    Post ID.
     * @param string $meta_key   Meta key.
     * @param mixed  $meta_value Meta value.
     */
    public function handleImageMetaChange($meta_id, $post_id, $meta_key, $meta_value): void
    {
        if ($meta_key !== '_thumbnail_id' && $meta_key !== '_product_image_gallery') {
            return;
        }

        $post_id = (int) $post_id;
        $post_type = get_post_type($post_id);
        if (!$post_type || in_array($post_type, ['attachment', 'revision', 'nav_menu_item'], true)) {
            return;
        }

        $parent_id = $this->resolveContextParentId($post_id);

        foreach ($this->parseAttachmentIds($meta_value) as $attachment_id) {
            $this->enqueueAttachment($attachment_id, $parent_id);
        }
    }

    /**
     * Re-queue a post's images when rule-relevant taxonomy terms change.
     *
     * @param int    $object_id  Object ID.
     * @param array  $terms      Terms.
     * @param array  $tt_ids     New term taxonomy IDs.
     * @param string $taxonomy   Taxonomy slug.
     * @param bool   $append     Whether terms were appended.
     * @param array  $old_tt_ids Previous term taxonomy IDs.
     */
    public function handleObjectTermsChange($object_id, $terms, $tt_ids, $taxonomy, $append, $old_tt_ids): void
    {
        /**
         * Taxonomies whose changes trigger re-evaluation of a post's images.
         *
         * @param string[] $taxonomies
         */
        $watched = (array) apply_filters('ultimate_watermark_rule_taxonomies', ['product_cat', 'product_tag', 'category']);
        if (!in_array($taxonomy, $watched, true)) {
            return;
        }

        $new = array_map('intval', (array) $tt_ids);
        $old = array_map('intval', (array) $old_tt_ids);
        sort($new);
        sort($old);
        if ($new === $old) {
            return;
        }

        $this->enqueuePostImages((int) $object_id);
    }

    /**
     * Queue a post's images the first time it leaves auto-draft.
     *
     * @param string   $new_status New status.
     * @param string   $old_status Old status.
     * @param \WP_Post $post       Post object.
     */
    public function handlePostStatusTransition($new_status, $old_status, $post): void
    {
        if ($old_status !== 'auto-draft' || $new_status === 'auto-draft' || !$post instanceof \WP_Post) {
            return;
        }

        $this->enqueuePostImages((int) $post->ID);

        // Images uploaded into the new post's editor (attached to it) were
        // deferred while it was an auto-draft.
        $attached = get_posts([
            'post_type'      => 'attachment',
            'post_mime_type' => 'image',
            'post_parent'    => (int) $post->ID,
            'post_status'    => 'inherit',
            'posts_per_page' => 200,
            'fields'         => 'ids',
            'no_found_rows'  => true,
        ]);
        $parent_id = $this->resolveContextParentId((int) $post->ID);
        foreach ($attached as $attachment_id) {
            $this->enqueueAttachment((int) $attachment_id, $parent_id);
        }
    }

    /**
     * Clean up after an attachment is deleted.
     *
     * @param int $attachment_id Attachment ID.
     */
    public function handleAttachmentDeleted($attachment_id): void
    {
        $attachment_id = (int) $attachment_id;
        if ($attachment_id <= 0) {
            return;
        }

        \MantraBrain\UltimateWatermark\Utils\WatermarkUsageTracker::cleanupImageUsage($attachment_id);
        \MantraBrain\UltimateWatermark\Utils\BackupManager::deleteAttachmentBackups($attachment_id);
    }

    /**
     * Queue the featured image, product gallery and variation images of a post.
     */
    public function enqueuePostImages(int $post_id): void
    {
        $post = get_post($post_id);
        if (!$post || in_array($post->post_type, ['attachment', 'revision', 'nav_menu_item'], true)) {
            return;
        }

        $parent_id = $this->resolveContextParentId($post_id);

        $ids = $this->parseAttachmentIds(get_post_meta($post_id, '_thumbnail_id', true));

        if ($post->post_type === 'product') {
            $ids = array_merge($ids, $this->parseAttachmentIds(get_post_meta($post_id, '_product_image_gallery', true)));

            $variation_ids = get_posts([
                'post_type'      => 'product_variation',
                'post_parent'    => $post_id,
                'post_status'    => 'any',
                'posts_per_page' => -1,
                'fields'         => 'ids',
                'no_found_rows'  => true,
            ]);
            foreach ($variation_ids as $variation_id) {
                $ids = array_merge($ids, $this->parseAttachmentIds(get_post_meta((int) $variation_id, '_thumbnail_id', true)));
            }
        }

        foreach (array_unique($ids) as $attachment_id) {
            $this->enqueueAttachment($attachment_id, $parent_id);
        }
    }

    /**
     * Add an attachment to the deferred queue.
     */
    public function enqueueAttachment(int $attachment_id, int $parent_id): void
    {
        if ($attachment_id <= 0 || $parent_id <= 0) {
            return;
        }

        $this->pendingQueue[$attachment_id] = $parent_id;
    }

    /**
     * Process every queued attachment. Safe to call repeatedly.
     */
    public function flushPendingQueue(): void
    {
        if ($this->flushing || empty($this->pendingQueue)) {
            return;
        }

        $this->flushing = true;

        try {
            // Processing may queue more work (it writes post meta), so drain until empty.
            $guard = 0;
            while (!empty($this->pendingQueue) && $guard++ < 10) {
                $batch = $this->pendingQueue;
                $this->pendingQueue = [];

                foreach ($batch as $attachment_id => $parent_id) {
                    try {
                        $this->processAttachmentForParent((int) $attachment_id, (int) $parent_id);
                    } catch (\Throwable $e) {
                        if (defined('WP_DEBUG') && WP_DEBUG) {
                            error_log('Ultimate Watermark: Failed to process attachment ' . $attachment_id . ' for post ' . $parent_id . ': ' . $e->getMessage());
                        }
                    }
                }
            }
        } finally {
            $this->flushing = false;
        }
    }

    /**
     * Apply the matching automatic watermarks to an image assigned to a post.
     *
     * Rules (post type, image size and unified conditions such as product
     * category) are evaluated against the parent post as it is saved now.
     * Images that are already watermarked, protected download files and
     * non-images are skipped, so calling this repeatedly is safe.
     *
     * @param int $attachment_id Attachment ID.
     * @param int $parent_id     Post the image belongs to (product, post, page…).
     * @return bool True when at least one watermark was applied.
     */
    public function processAttachmentForParent(int $attachment_id, int $parent_id): bool
    {
        if ($attachment_id <= 0 || $parent_id <= 0 || isset($this->processing[$attachment_id])) {
            return false;
        }

        if (!wp_attachment_is_image($attachment_id) || AttachmentGuard::shouldSkip($attachment_id)) {
            return false;
        }

        if (get_post_meta($attachment_id, '_ulwm_skip_watermarking', true)) {
            return false;
        }

        // Never stack watermarks on an image that already carries one.
        $applied = get_post_meta($attachment_id, 'applied_watermarks', true);
        if (!empty($applied)) {
            return false;
        }

        $parent = get_post($parent_id);
        if (!$parent || in_array($parent->post_status, ['auto-draft', 'trash'], true)) {
            // Auto-drafts have no terms yet; they are re-queued when first saved.
            return false;
        }

        $this->processing[$attachment_id] = true;

        try {
            $watermarks = $this->getWatermarksForParent($attachment_id, $parent);
            if (empty($watermarks)) {
                return false;
            }

            update_post_meta($attachment_id, '_ulwm_uploaded_to_post_id', $parent_id);

            $applied = $this->applyWatermarksForFeaturedImage(
                $attachment_id,
                $watermarks['watermarks'],
                $parent_id,
                $watermarks['override']
            );
        } finally {
            unset($this->processing[$attachment_id]);
        }

        if ($applied) {
            $this->protectCropSource($attachment_id, $parent_id);
        }

        return $applied;
    }

    /**
     * A cropped copy (Dokan's product-image crop step, the WordPress cropper)
     * was just watermarked: watermark the full-size image it was cropped from
     * too, under the same rules, so the clean original is not left at a public
     * URL in the media library.
     */
    private function protectCropSource(int $attachment_id, int $parent_id): void
    {
        $source_id = (int) get_post_meta($attachment_id, '_ulwm_crop_source', true);
        if ($source_id <= 0 || $source_id === $attachment_id) {
            return;
        }

        /**
         * Whether to also watermark the image a watermarked crop was made from.
         *
         * @param bool $protect       Default true.
         * @param int  $source_id     Attachment the crop was made from.
         * @param int  $attachment_id The watermarked crop.
         */
        if (!apply_filters('ultimate_watermark_protect_crop_source', true, $source_id, $attachment_id)) {
            return;
        }

        // Same checks as any image: protected files, already-watermarked
        // images and rule mismatches are skipped there.
        $this->processAttachmentForParent($source_id, $parent_id);
    }

    /**
     * Apply one specific watermark to every size of an image (explicit user
     * action such as a batch job). Protected download files and images that
     * already carry a watermark are skipped; a backup is created first.
     *
     * @return bool True when the watermark was applied.
     */
    public function applySpecificWatermark(int $attachment_id, int $watermark_id): bool
    {
        if ($attachment_id <= 0 || $watermark_id <= 0 || isset($this->processing[$attachment_id])) {
            return false;
        }

        if (!wp_attachment_is_image($attachment_id) || AttachmentGuard::shouldSkip($attachment_id)) {
            return false;
        }

        if (!empty(get_post_meta($attachment_id, 'applied_watermarks', true))) {
            return false;
        }

        $watermark = WatermarkHelper::getActiveWatermarkById($watermark_id);
        if (!$watermark) {
            return false;
        }

        $this->processing[$attachment_id] = true;
        try {
            return $this->applyWatermarksForFeaturedImage(
                $attachment_id,
                [$watermark],
                WatermarkHelper::resolveParentPostId($attachment_id),
                true
            );
        } finally {
            unset($this->processing[$attachment_id]);
        }
    }

    /**
     * Resolve which watermarks are candidates for an image of a given post.
     *
     * @return array{watermarks: array, override: bool}|array Empty array when none apply.
     */
    private function getWatermarksForParent(int $attachment_id, \WP_Post $parent): array
    {
        /**
         * Explicit watermark assignment for an image (e.g. Pro's per-product or
         * per-category override). When non-empty, these watermarks are applied
         * instead of the automatic, rule-based ones.
         *
         * @param int[] $watermark_ids Watermark IDs.
         * @param int   $attachment_id Attachment ID.
         * @param int   $parent_id     Parent post ID.
         */
        $override_ids = (array) apply_filters('ultimate_watermark_override_watermark_ids', [], $attachment_id, (int) $parent->ID);
        $override_ids = array_values(array_unique(array_filter(array_map('intval', $override_ids))));

        if (!empty($override_ids)) {
            $override = [];
            foreach ($override_ids as $watermark_id) {
                $data = WatermarkHelper::getActiveWatermarkById($watermark_id);
                if ($data) {
                    $override[] = $data;
                }
            }
            if (!empty($override)) {
                return ['watermarks' => $override, 'override' => true];
            }
        }

        $frontend_upload = $this->isFrontendUpload($attachment_id);
        $automatic = array_filter(WatermarkHelper::getActiveWatermarks(), static function ($watermark) use ($frontend_upload) {
            if (($watermark['automatic_watermarking'] ?? '0') === '1' || ($watermark['automatic_watermarking'] ?? false) === true) {
                return true;
            }
            // "Frontend watermarking": also used for images uploaded from the front end.
            return $frontend_upload && ($watermark['frontend_watermarking'] ?? '0') === '1';
        });

        $parent_type = (string) $parent->post_type;
        $matching = array_filter($automatic, function ($watermark) use ($parent_type) {
            return $this->watermarkAllowsPostType($watermark, $parent_type);
        });

        return empty($matching) ? [] : ['watermarks' => array_values($matching), 'override' => false];
    }

    /**
     * Legacy "Watermark on" check against the parent post type.
     */
    private function watermarkAllowsPostType(array $watermark, string $post_type): bool
    {
        $watermark_on = $watermark['watermark_on'] ?? 'everywhere';

        if ($watermark_on === 'everywhere' || $watermark_on === '') {
            return true;
        }

        if ($watermark_on !== 'selected_post_types') {
            return false;
        }

        $allowed = $watermark['watermark_post_types'] ?? [];
        if (is_string($allowed)) {
            $allowed = maybe_unserialize($allowed);
            if (is_string($allowed)) {
                $allowed = maybe_unserialize($allowed);
            }
        }
        if (!is_array($allowed)) {
            return false;
        }

        return in_array($post_type, array_map('strval', array_values($allowed)), true);
    }

    /**
     * Whether a watermark carries unified (condition-based) rules.
     */
    /**
     * Whether a watermark's rules need the post the image belongs to
     * (categories, tags, post type) to be evaluated meaningfully.
     */
    private function rulesDependOnParent(array $watermark): bool
    {
        foreach ((array) ($watermark['watermark_rules'] ?? []) as $rule) {
            foreach ((array) ($rule['conditions'] ?? []) as $condition) {
                if (in_array($condition['type'] ?? '', ['post_type', 'post_category', 'product_cat', 'product_tag'], true)) {
                    return true;
                }
            }
        }

        return false;
    }

    private function hasUnifiedConditions(array $watermark): bool
    {
        $rules = $watermark['watermark_rules'] ?? [];
        if (empty($rules) || !is_array($rules)) {
            return false;
        }

        foreach ($rules as $rule) {
            if (is_array($rule) && !empty($rule['conditions']) && is_array($rule['conditions'])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Variations are evaluated in the context of their parent product, since
     * categories and tags live on the product, not on the variation.
     */
    private function resolveContextParentId(int $post_id): int
    {
        if (get_post_type($post_id) === 'product_variation') {
            $product_id = (int) wp_get_post_parent_id($post_id);
            if ($product_id > 0) {
                return $product_id;
            }
        }

        return $post_id;
    }

    /**
     * Normalize a meta value (int, numeric string, comma list or array) into attachment IDs.
     *
     * @param mixed $value
     * @return int[]
     */
    private function parseAttachmentIds($value): array
    {
        if (is_string($value)) {
            $value = maybe_unserialize($value);
        }

        if (is_numeric($value)) {
            $value = [(int) $value];
        } elseif (is_string($value)) {
            $value = explode(',', $value);
        }

        if (!is_array($value)) {
            return [];
        }

        return array_values(array_unique(array_filter(array_map('absint', $value))));
    }

    /**
     * Apply watermarks to an image that belongs to a post.
     *
     * Respects post type rules (already filtered by the caller), image size
     * rules and unified watermark_rules conditions (product_cat, etc.).
     * A backup is created only when at least one size will be watermarked.
     *
     * @param int   $attachmentId       The attachment ID.
     * @param array $matchingWatermarks Candidate watermarks.
     * @param int   $parentPostId       The parent post ID.
     * @param bool  $isOverride         Explicit assignment: skip rule evaluation.
     * @return bool True when at least one size was watermarked.
     */
    private function applyWatermarksForFeaturedImage(int $attachmentId, array $matchingWatermarks, int $parentPostId, bool $isOverride = false): bool
    {
        $imageSizes = get_intermediate_image_sizes();
        $imageSizes[] = 'full';

        $plan = [];
        foreach ($imageSizes as $size) {
            $watermarksForSize = [];

            foreach ($matchingWatermarks as $watermark) {
                if (!$isOverride) {
                    if ($this->hasUnifiedConditions($watermark)) {
                        $evalContext = \MantraBrain\UltimateWatermark\Utils\RulesEvaluator::buildContext(
                            $attachmentId,
                            $size,
                            $parentPostId
                        );

                        if (!\MantraBrain\UltimateWatermark\Utils\RulesEvaluator::evaluate($watermark['watermark_rules'], $evalContext)) {
                            continue;
                        }
                    } elseif (!WatermarkHelper::shouldApplyWatermarkByImageSize($watermark, $size)) {
                        continue;
                    }
                }

                $watermarksForSize[] = $watermark;
            }

            if (!empty($watermarksForSize)) {
                $plan[$size] = $watermarksForSize;
            }
        }

        if (empty($plan)) {
            return false;
        }

        // Back up the clean files before anything is modified.
        if (!$this->createBackupForWatermarking($attachmentId)) {
            return false;
        }

        foreach ($plan as $size => $watermarks) {
            foreach ($watermarks as $watermark) {
                $this->applyWatermarkToAttachmentSize($attachmentId, $watermark, (string) $size);
            }
        }

        delete_post_meta($attachmentId, '_ulwm_watermarked');

        return !empty(get_post_meta($attachmentId, 'applied_watermarks', true));
    }

    /**
     * Capture watermark toggle state from REST API request before raw body is consumed.
     *
     * When images are uploaded via the media popup (e.g., WooCommerce featured image),
     * plupload sends the watermark toggle state and selected watermark IDs as multipart
     * form fields alongside the file. This method captures those values early in the
     * REST API request lifecycle, before WordPress consumes the raw request body for
     * file processing, and stores them in a static property for later retrieval.
     *
     * @param WP_REST_Response|WP_Error|mixed $response Result to send to the client.
     * @param array                          $handler  Route handler used for the request.
     * @param WP_REST_Request|mixed          $request  Request used to generate the response.
     * @return WP_REST_Response|WP_Error|mixed
     */
    public function captureWatermarkToggleFromRequest($response, $handler, $request)
    {
        // CRITICAL: Some contexts (like WooCommerce rest_preload_api_request) pass
        // an array as the second argument instead of WP_REST_Request. Return early.
        if (!$request instanceof \WP_REST_Request) {
            return $response;
        }
        // Only process media endpoint requests
        $route = $request->get_route();
        if (strpos($route, '/wp/v2/media') === false) {
            return $response;
        }

        // Only process POST requests (uploads)
        if (strtoupper($request->get_method()) !== 'POST') {
            return $response;
        }

        // Check for toggle state in request parameters
        $toggleEnabled = false;
        $selectedWatermarkIds = [];

        // Check REST request parameters for toggle state
        $toggleParam = $request->get_param('ultimate_watermark_auto_apply');
        if ($toggleParam === '1' || $toggleParam === 1 || $toggleParam === true) {
            $toggleEnabled = true;
        }

        // Get selected watermark IDs from REST request parameters
        if ($toggleEnabled) {
            $idsParam = $request->get_param('ultimate_watermark_ids');
            if ($idsParam !== null && $idsParam !== '') {
                if (is_string($idsParam)) {
                    $selectedWatermarkIds = array_filter(array_map('absint', explode(',', trim($idsParam))));
                } elseif (is_array($idsParam)) {
                    $selectedWatermarkIds = array_filter(array_map('absint', $idsParam));
                }
            }
        }

        // Also check $_POST/$_REQUEST as fallback
        if (!$toggleEnabled) {
            if (isset($_POST['ultimate_watermark_auto_apply']) && $_POST['ultimate_watermark_auto_apply'] === '1') {
                $toggleEnabled = true;
            } elseif (isset($_REQUEST['ultimate_watermark_auto_apply']) && $_REQUEST['ultimate_watermark_auto_apply'] === '1') {
                $toggleEnabled = true;
            }
        }

        if ($toggleEnabled && empty($selectedWatermarkIds)) {
            if (isset($_POST['ultimate_watermark_ids']) && !empty($_POST['ultimate_watermark_ids'])) {
                $selectedWatermarkIds = array_filter(array_map('absint', explode(',', sanitize_text_field($_POST['ultimate_watermark_ids']))));
            } elseif (isset($_REQUEST['ultimate_watermark_ids']) && !empty($_REQUEST['ultimate_watermark_ids'])) {
                $selectedWatermarkIds = array_filter(array_map('absint', explode(',', sanitize_text_field($_REQUEST['ultimate_watermark_ids']))));
            }
        }

        // Store in static property for later retrieval by markForWatermarking and handleRestApiAttachmentUpload
        if ($toggleEnabled) {
            self::$capturedToggleState = [
                'enabled' => true,
                'watermark_ids' => $selectedWatermarkIds,
            ];
        }

        return $response;
    }

    /**
     * Captured toggle state from REST API request (set by captureWatermarkToggleFromRequest).
     *
     * @var array|null
     */
    private static ?array $capturedToggleState = null;

    /**
     * Handle REST API attachment upload (for page/post editor)
     * This is called when image is uploaded via REST API from page/post editor
     */
    public function handleRestApiAttachmentUpload(\WP_Post $attachment, \WP_REST_Request $request, bool $creating): void
    {
        if (!$creating || !wp_attachment_is_image($attachment->ID) || AttachmentGuard::shouldSkip((int) $attachment->ID)) {
            return;
        }
        
        // Check if attachment has a parent post (uploaded to a page/post)
        $parent_post_id = $attachment->post_parent;
        
        // Also check REST request parameter 'post'
        if ($parent_post_id <= 0) {
            $post_param = $request->get_param('post');
            if ($post_param && is_numeric($post_param) && $post_param > 0) {
                $parent_post_id = absint($post_param);
            }
        }
        
        // If we have a parent post, store it for rule checking later
        if ($parent_post_id > 0) {
            // Store parent post_id for later use in processAfterMetadataGeneration
            update_post_meta($attachment->ID, '_ulwm_uploaded_to_post_id', $parent_post_id);
        }
        
        // CRITICAL FIX: Check if the watermark toggle was enabled in the media popup
        // When users upload via the media popup (e.g., WooCommerce featured image),
        // the toggle state is sent as a REST API parameter. We need to detect this
        // and set the appropriate meta flags so watermarks are applied.
        $toggleEnabled = false;
        $selectedWatermarkIds = [];
        
        // Check REST request parameters for toggle state
        $toggleParam = $request->get_param('ultimate_watermark_auto_apply');
        if ($toggleParam === '1' || $toggleParam === 1 || $toggleParam === true) {
            $toggleEnabled = true;
        }
        
        // Also check HTTP headers as fallback (set by client-side code)
        if (!$toggleEnabled) {
            $toggleHeader = $request->get_header('x-ultimate-watermark-auto-apply');
            if ($toggleHeader === '1' || $toggleHeader === 1) {
                $toggleEnabled = true;
            }
        }
        
        // Also check $_POST/$_REQUEST as fallback for sideload uploads
        if (!$toggleEnabled) {
            if (isset($_POST['ultimate_watermark_auto_apply']) && $_POST['ultimate_watermark_auto_apply'] === '1') {
                $toggleEnabled = true;
            } elseif (isset($_REQUEST['ultimate_watermark_auto_apply']) && $_REQUEST['ultimate_watermark_auto_apply'] === '1') {
                $toggleEnabled = true;
            }
        }
        
        if ($toggleEnabled) {
            // Get selected watermark IDs from REST request parameters
            $idsParam = $request->get_param('ultimate_watermark_ids');
            if ($idsParam !== null && $idsParam !== '') {
                if (is_string($idsParam)) {
                    $selectedWatermarkIds = array_filter(array_map('absint', explode(',', trim($idsParam))));
                } elseif (is_array($idsParam)) {
                    $selectedWatermarkIds = array_filter(array_map('absint', $idsParam));
                }
            }
            
            // Fallback: check $_POST/$_REQUEST for watermark IDs
            if (empty($selectedWatermarkIds)) {
                if (isset($_POST['ultimate_watermark_ids']) && !empty($_POST['ultimate_watermark_ids'])) {
                    $selectedWatermarkIds = array_filter(array_map('absint', explode(',', sanitize_text_field($_POST['ultimate_watermark_ids']))));
                } elseif (isset($_REQUEST['ultimate_watermark_ids']) && !empty($_REQUEST['ultimate_watermark_ids'])) {
                    $selectedWatermarkIds = array_filter(array_map('absint', explode(',', sanitize_text_field($_REQUEST['ultimate_watermark_ids']))));
                }
            }
            
            // Store selected watermark IDs in attachment meta
            update_post_meta($attachment->ID, '_ulwm_selected_watermark_ids', $selectedWatermarkIds);
            
            // Mark this attachment for watermarking
            update_post_meta($attachment->ID, '_ulwm_watermarked', true);
        }
    }

    /**
     * Handle attachment insert via wp_insert_post (catches ALL methods including REST API)
     */
    public function handleAttachmentPostInsert(int $post_id, \WP_Post $post, bool $update): void
    {
        // Only process new attachments (not updates) that are images
        if ($update || $post->post_type !== 'attachment' || !wp_attachment_is_image($post_id)) {
            return;
        }
        
        // Check if attachment has a parent post (uploaded to a page/post)
        // CRITICAL: Do NOT set _ulwm_watermarked here - that flag is ONLY for toggle-based uploads
        // Rule-based watermarking will be handled in processAfterMetadataGeneration
        if ($post->post_parent > 0) {
            // Store parent post_id for later use in processAfterMetadataGeneration
            update_post_meta($post_id, '_ulwm_uploaded_to_post_id', $post->post_parent);
        }
    }

    /**
     * Process new attachment after upload (for REST API uploads)
     */
    public function markForWatermarking(int $attachment_id): void
    {
        // Downloadable product files (WooCommerce / Dokan) are never watermarked.
        if (AttachmentGuard::isDownloadUploadRequest() || AttachmentGuard::shouldSkip($attachment_id)) {
            return;
        }

        $this->flagUploadOrigin($attachment_id);

        // Check if auto-apply is enabled via toggle (for media library uploads)
        // For REST API uploads, we'll check watermark rules based on parent post type
        $auto_apply_enabled = false;
        
        // CRITICAL: Check if the parameter was sent in the request (even if '0')
        // If parameter exists in POST/REQUEST, use that value (it's from plupload multipart_params)
        // Only use WordPress option fallback if parameter is completely absent
        $toggle_param_sent = isset($_POST['ultimate_watermark_auto_apply']) || isset($_REQUEST['ultimate_watermark_auto_apply']);
        
        if ($toggle_param_sent) {
            // Parameter was sent - use its value (could be '1' or '0' or anything else)
            $toggle_value = isset($_POST['ultimate_watermark_auto_apply']) 
                ? $_POST['ultimate_watermark_auto_apply'] 
                : $_REQUEST['ultimate_watermark_auto_apply'];
            
            if ($toggle_value === '1') {
                $auto_apply_enabled = true;
            } else {
                // Parameter sent but not '1' - explicitly disabled
                $auto_apply_enabled = false;
            }
        } else {
            // Parameter not sent at all - use WordPress option fallback
            // This handles uploads from other sources (not via media library toggle)
            $option_value = (string) get_user_meta(get_current_user_id(), 'ultimate_watermark_auto_apply_toggle', true);
            if ($option_value === '1') {
                $auto_apply_enabled = true;
            }
        }
        
        // If toggle is ON, mark for watermarking (media library uploads)
        if ($auto_apply_enabled) {
            // CRITICAL: Capture parent post_id if image is uploaded to a page/post
            $parent_post_id = 0;
            
            // 1. Check $_POST['post_id'] (standard form upload)
            if (isset($_POST['post_id']) && $_POST['post_id'] > 0) {
                $parent_post_id = absint($_POST['post_id']);
            }
            // 2. Check $_REQUEST['post_id'] (fallback)
            elseif (isset($_REQUEST['post_id']) && $_REQUEST['post_id'] > 0) {
                $parent_post_id = absint($_REQUEST['post_id']);
            }
            // 3. Check attachment's post_parent (if already set)
            else {
                $attachment = get_post($attachment_id);
                if ($attachment && $attachment->post_parent > 0) {
                    $parent_post_id = $attachment->post_parent;
                }
            }
            
            // Store parent post_id in attachment meta for rule checking later
            if ($parent_post_id > 0) {
                update_post_meta($attachment_id, '_ulwm_uploaded_to_post_id', $parent_post_id);
            }
            
            // Get selected watermark IDs from upload parameters
            $selected_watermark_ids = [];
            
            // Check if parameter exists (even if empty - empty string means user wants NO watermarks)
            // IMPORTANT: plupload sends multipart_params as POST data, but they might be in $_FILES context
            // Also check all possible places where the data could be
            $ids_string = '';
            
            // 1. Check $_POST first (standard location - from multipart_params)
            if (isset($_POST['ultimate_watermark_ids'])) {
                $ids_string = sanitize_text_field($_POST['ultimate_watermark_ids']);
            }
            // 2. Check $_REQUEST (broader scope)
            elseif (isset($_REQUEST['ultimate_watermark_ids'])) {
                $ids_string = sanitize_text_field($_REQUEST['ultimate_watermark_ids']);
            }
            // 3. Check WordPress option (set by AJAX before upload)
            elseif ((string) get_user_meta(get_current_user_id(), 'ultimate_watermark_temp_selected_ids', true) !== '') {
                $ids_string = sanitize_text_field((string) get_user_meta(get_current_user_id(), 'ultimate_watermark_temp_selected_ids', true));
                // Clear after reading (one-time use)
                delete_user_meta(get_current_user_id(), 'ultimate_watermark_temp_selected_ids');
            }
            // 4. Check raw input as last resort
            else {
                $raw_input = file_get_contents('php://input');
                if (preg_match('/name="ultimate_watermark_ids"[^>]*>([^<]+)</i', $raw_input, $matches)) {
                    $ids_string = sanitize_text_field($matches[1]);
                }
                // Also try parsing the entire raw input
                elseif (preg_match('/ultimate_watermark_ids[\'"\']?\s*[:=]\s*[\'"\']?([^\'"&,]+)/i', $raw_input, $matches)) {
                    $ids_string = sanitize_text_field($matches[1]);
                }
            }
            
            // Process the IDs string
            if (!empty($ids_string) && trim($ids_string) !== '') {
                $selected_watermark_ids = array_filter(array_map('absint', explode(',', trim($ids_string))));
            } else {
                // If empty string, keep as empty array (user explicitly unchecked all)
                $selected_watermark_ids = [];
            }
            
            // Store selected watermark IDs in attachment meta (even if empty, to track user intent)
            // CRITICAL: Always store as array, never as string
            update_post_meta($attachment_id, '_ulwm_selected_watermark_ids', $selected_watermark_ids);
            
            // Mark this attachment for watermarking
            update_post_meta($attachment_id, '_ulwm_watermarked', true);
            
            // DO NOT reset the toggle here - it will be reset after watermark is applied
            // Resetting it here causes race conditions where metadata generation happens before toggle check
        }
        // If toggle is OFF, don't mark - let REST API hooks or wp_insert_post handle it
    }

    /**
     * Process after metadata generation to apply watermarks
     */
    public function processAfterMetadataGeneration($metadata, $attachment_id)
    {
        // CRITICAL: Skip watermarking during restore operations
        // Restore sets _ulwm_skip_watermarking flag to prevent re-watermarking
        if (get_post_meta($attachment_id, '_ulwm_skip_watermarking', true)) {
            return $metadata;
        }

        // Protected download files (e.g. uploads/woocommerce_uploads) stay untouched.
        if (!is_array($metadata) || AttachmentGuard::shouldSkip((int) $attachment_id)) {
            return $metadata;
        }

        // Metadata is regenerated from the already-watermarked file (thumbnail
        // regeneration, image editor, Pro bulk tools). Watermarking again here
        // would stack a second watermark on every size.
        $already_applied = get_post_meta($attachment_id, 'applied_watermarks', true);
        if (!empty($already_applied)) {
            return $metadata;
        }
        
        // Check if this image should be watermarked
        // _ulwm_watermarked flag is ONLY set by toggle (media library uploads with toggle ON)
        // For REST API uploads, we check rules directly without setting this flag
        $was_toggle_enabled = get_post_meta($attachment_id, '_ulwm_watermarked', true);

        // Toggle on but no watermark picked: fall back to rule-based watermarking.
        if ($was_toggle_enabled) {
            $selected = get_post_meta($attachment_id, '_ulwm_selected_watermark_ids', true);
            if (empty($selected)) {
                $was_toggle_enabled = false;
                delete_post_meta($attachment_id, '_ulwm_watermarked');
                delete_post_meta($attachment_id, '_ulwm_selected_watermark_ids');
            }
        }
        
        // Determine if we should watermark based on rules (for REST API uploads)
        $should_watermark_by_rules = false;
        
        // If not marked via toggle, check if it was uploaded to a page/post via REST API
        // Use the stored _ulwm_uploaded_to_post_id which was set during upload
        if (!$was_toggle_enabled && wp_attachment_is_image($attachment_id)) {
            // First, try to get from stored meta (set during upload)
            $parent_post_id = get_post_meta($attachment_id, '_ulwm_uploaded_to_post_id', true);
            
            // Fallback: check attachment's post_parent
            if (!$parent_post_id || $parent_post_id <= 0) {
                $attachment = get_post($attachment_id);
                if ($attachment && $attachment->post_parent > 0) {
                    $parent_post_id = $attachment->post_parent;
                }
            }
            
            // Get all active automatic watermarks
            $all_active = WatermarkHelper::getActiveWatermarks();
            $automatic_watermarks = array_filter($all_active, function($watermark) {
                return $watermark['automatic_watermarking'] === '1' || (boolean)$watermark['automatic_watermarking'] === true;
            });
            
            if (!empty($automatic_watermarks)) {
                if ($parent_post_id > 0) {
                    $parent_post = get_post($parent_post_id);
                    // A new post is still an auto-draft (no categories yet): its
                    // images are evaluated when it is first saved.
                    if ($parent_post && in_array($parent_post->post_status, ['auto-draft', 'trash'], true)) {
                        $parent_post = null;
                    }
                    if ($parent_post) {
                        // Check if any watermarks match the parent post type rules
                        $parent_post_type = $parent_post->post_type;
                        
                        // Filter by post type rules
                        $matching_watermarks = array_filter($automatic_watermarks, function($watermark) use ($parent_post_type) {
                            $watermark_on = $watermark['watermark_on'] ?? 'everywhere';
                            
                            if ($watermark_on === 'everywhere' || $watermark_on === '') {
                                return true;
                            }
                            
                            if ($watermark_on === 'selected_post_types') {
                                $allowed_post_types = $watermark['watermark_post_types'] ?? [];
                                
                                if (is_string($allowed_post_types)) {
                                    $allowed_post_types = maybe_unserialize($allowed_post_types);
                                    if (is_string($allowed_post_types)) {
                                        $allowed_post_types = maybe_unserialize($allowed_post_types);
                                    }
                                }
                                
                                if (!is_array($allowed_post_types)) {
                                    $allowed_post_types = [];
                                }
                                
                                $allowed_post_types = array_map('strval', array_values($allowed_post_types));
                                
                                // For uploads from page/post editor, check parent post type only
                                return in_array(strval($parent_post_type), $allowed_post_types, true);
                            }
                            
                            return false;
                        });
                        
                        if (!empty($matching_watermarks)) {
                            $should_watermark_by_rules = true;
                        }
                    }
                }
                // REMOVED: No longer apply watermarks to direct media library uploads without toggle
                // If user uploads to media library with toggle OFF, watermarks should NOT be applied
                // Watermarks only apply when:
                // 1. Toggle is ON (handled by $was_toggle_enabled)
                // 2. Image uploaded via REST API to a page/post with matching rules (handled above)
            }
        }
        
        // Watermark if toggle was enabled OR rules match
        if ($was_toggle_enabled || $should_watermark_by_rules) {
            // Raise (never lower) the memory limit for image processing.
            wp_raise_memory_limit('image');

            try {
                // Create backup BEFORE applying watermarks (skip the image if it fails).
                if ($this->createBackupForWatermarking($attachment_id)) {
                    // Toggle uploads apply the user's selection; otherwise filter by rules.
                    $this->applyWatermarksToGeneratedImages($attachment_id, (bool) $was_toggle_enabled);
                }
            } catch (\Throwable $e) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('Ultimate Watermark: Failed to watermark attachment ' . $attachment_id . ': ' . $e->getMessage());
                }
            } finally {
                // Remove the one-shot toggle flags so the image is never re-processed by them.
                if ($was_toggle_enabled) {
                    delete_post_meta($attachment_id, '_ulwm_watermarked');
                    delete_post_meta($attachment_id, '_ulwm_selected_watermark_ids');
                }
            }
        }
        
        // Images uploaded from the front end of the site (vendor dashboards,
        // submission forms) that are not attached to a post yet: apply the
        // watermarks marked "Frontend watermarking" whose rules match now.
        // Watermarks whose rules depend on the post (category, post type) are
        // left for later: they are evaluated once the image is assigned to it.
        if (!$was_toggle_enabled && $this->isFrontendUpload((int) $attachment_id) && empty(get_post_meta($attachment_id, 'applied_watermarks', true))) {
            $parent_id = (int) get_post_meta($attachment_id, '_ulwm_uploaded_to_post_id', true) ?: (int) wp_get_post_parent_id($attachment_id);
            if ($parent_id <= 0) {
                $frontend = array_values(array_filter(WatermarkHelper::getActiveWatermarks(), function ($watermark) {
                    return ($watermark['frontend_watermarking'] ?? '0') === '1'
                        && $this->watermarkAllowsPostType($watermark, 'attachment')
                        && !$this->rulesDependOnParent($watermark);
                }));
                if (!empty($frontend)) {
                    wp_raise_memory_limit('image');
                    try {
                        $this->applyWatermarksForFeaturedImage((int) $attachment_id, $frontend, 0);
                    } catch (\Throwable $e) {
                        if (defined('WP_DEBUG') && WP_DEBUG) {
                            error_log('Ultimate Watermark: Frontend watermarking failed for attachment ' . $attachment_id . ': ' . $e->getMessage());
                        }
                    }
                }
            }
        }

        return $metadata;
    }

    /**
     * Record where an upload came from.
     *
     * - Uploads made from a page outside wp-admin (Dokan dashboard, front-end
     *   forms) are flagged as front-end uploads for "Frontend watermarking".
     * - A crop of an already watermarked image (Dokan / Customizer crop step)
     *   inherits that state, so the copy is never watermarked a second time.
     */
    private function flagUploadOrigin(int $attachment_id): void
    {
        // phpcs:disable WordPress.Security.NonceVerification -- read-only classification; core verified the upload nonce.
        // Core ('crop-image', 'custom-header-crop'), Dokan and most media
        // croppers post the source attachment ID together with cropDetails.
        $is_crop = !empty($_POST['id']) && !empty($_POST['cropDetails']);
        if ($is_crop) {
            $source_id = absint($_POST['id']);
            if ($source_id > 0 && $source_id !== $attachment_id) {
                update_post_meta($attachment_id, '_ulwm_crop_source', $source_id);
            }
            $source_applied = get_post_meta($source_id, 'applied_watermarks', true);
            if (!empty($source_applied)) {
                update_post_meta($attachment_id, 'applied_watermarks', $source_applied);
            }
            if (get_post_meta($source_id, '_ulwm_frontend_upload', true) === '1') {
                update_post_meta($attachment_id, '_ulwm_frontend_upload', '1');
            }
            return;
        }
        // phpcs:enable

        $referer = wp_get_raw_referer();
        // Compare host + path only: http/https or mapped-domain differences
        // must not turn an admin upload into a "front-end" one.
        $strip = static function ($url) {
            $parts = wp_parse_url((string) $url);
            return strtolower(($parts['host'] ?? '') . ($parts['path'] ?? ''));
        };
        $referer_key = $referer ? $strip($referer) : '';
        $is_frontend = $referer_key !== ''
            && strpos($referer_key, $strip(admin_url())) !== 0
            && strpos($referer_key, $strip(network_admin_url())) !== 0;

        /**
         * Filter whether an upload counts as a front-end upload.
         *
         * @param bool $is_frontend   Detected from the HTTP referer.
         * @param int  $attachment_id Attachment ID.
         */
        if (apply_filters('ultimate_watermark_is_frontend_upload', $is_frontend, $attachment_id)) {
            update_post_meta($attachment_id, '_ulwm_frontend_upload', '1');
        }
    }

    /**
     * Whether an attachment was uploaded from the front end.
     */
    private function isFrontendUpload(int $attachment_id): bool
    {
        return get_post_meta($attachment_id, '_ulwm_frontend_upload', true) === '1';
    }

    /**
     * Create backup for watermarking
     */
    private function createBackupForWatermarking(int $attachment_id): bool
    {
        // Backups switched off: watermark without one (the site owner's choice).
        if (!\MantraBrain\UltimateWatermark\Utils\BackupManager::isBackupEnabled()) {
            return true;
        }

        // Get the original image file path using get_attached_file
        // This should be the original unwatermarked file at this point
        $original_path = get_attached_file($attachment_id);
        if (!$original_path || !file_exists($original_path)) {
            return false;
        }

        // CRITICAL: Verify this is the actual original file, not a watermarked version
        // Check metadata to get the original_image if it exists (WordPress big image feature)
        $metadata = wp_get_attachment_metadata($attachment_id);
        $upload_dir = wp_upload_dir();
        
        // If WordPress has stored an original_image (before scaling), use that for backup
        // This is the true original before any WordPress processing
        if (!empty($metadata['original_image'])) {
            $original_image_path = $upload_dir['basedir'] . '/' . dirname($metadata['file']) . '/' . $metadata['original_image'];
            if (file_exists($original_image_path)) {
                $original_path = $original_image_path;
            }
        }

        // Get all image sizes for multi-size backup
        $image_sizes = get_intermediate_image_sizes();
        $image_sizes[] = 'full';
        
        // Collect paths for all image sizes
        // IMPORTANT: At this point, these files should be unwatermarked
        // because we're inside processAfterMetadataGeneration which runs AFTER
        // WordPress generates thumbnails but BEFORE we apply watermarks
        $backup_paths = [];
        foreach ($image_sizes as $size) {
            if ($size === 'full') {
                // For full size, use the original path we determined above
                if (file_exists($original_path)) {
                    $backup_paths['full'] = $original_path;
                }
            } else {
                // For other sizes, get from metadata (generated by WordPress, should be unwatermarked)
                $image_path = $this->getImagePathForSize($attachment_id, $size);
                if ($image_path && file_exists($image_path)) {
                    $backup_paths[$size] = $image_path;
                }
            }
        }
        
        // Ensure we have at least the full size
        if (empty($backup_paths)) {
            $backup_paths['full'] = $original_path;
        } elseif (!isset($backup_paths['full'])) {
            $backup_paths['full'] = $original_path;
        }
        
        // Create backup with all sizes. Backups are on, so never modify an image
        // whose original could not be saved (full disk, unwritable folder).
        $backup = \MantraBrain\UltimateWatermark\Utils\BackupManager::createBackup(
            $backup_paths['full'],
            $attachment_id,
            0,
            $backup_paths
        );

        return !empty($backup);
    }

    /**
     * Apply watermarks to generated images
     */
    private function applyWatermarksToGeneratedImages(int $attachment_id, bool $use_toggle_rules = false): void
    {
        // If use_toggle_rules is true, this means toggle was enabled (media library upload with toggle ON)
        // In that case, apply SELECTED automatic watermarks (if specified) or ALL if none selected
        // If false, this is rule-based (REST API upload from page/post editor), so filter by rules
        $was_toggle_enabled = $use_toggle_rules;
        
        // Get selected watermark IDs from attachment meta (set during upload)
        $selected_watermark_ids = get_post_meta($attachment_id, '_ulwm_selected_watermark_ids', true);
        
        // CRITICAL: Handle all possible return types from get_post_meta
        if ($selected_watermark_ids === false || $selected_watermark_ids === '' || $selected_watermark_ids === null) {
            $selected_watermark_ids = [];
        } elseif (!is_array($selected_watermark_ids)) {
            // If it's a string (comma-separated), convert to array
            if (is_string($selected_watermark_ids) && !empty(trim($selected_watermark_ids))) {
                $selected_watermark_ids = array_filter(array_map('absint', explode(',', trim($selected_watermark_ids))));
            } else {
                $selected_watermark_ids = [];
            }
        }
        
        // Ensure all IDs are integers
        $selected_watermark_ids = array_map('intval', $selected_watermark_ids);
        $selected_watermark_ids = array_filter($selected_watermark_ids, function($id) {
            return $id > 0;
        });
        
        
        // Get parent post ID for rule checking (for REST API uploads from page/post editor)
        $parent_post_id = get_post_meta($attachment_id, '_ulwm_uploaded_to_post_id', true);
        if (!$parent_post_id || $parent_post_id <= 0) {
            $attachment = get_post($attachment_id);
            if ($attachment && $attachment->post_parent > 0) {
                $parent_post_id = $attachment->post_parent;
            }
        }
        
        // Get all registered image sizes
        $image_sizes = get_intermediate_image_sizes();
        $image_sizes[] = 'full';
        
        // Apply watermarks to each size based on their rules
        // We need to check rules for EACH size separately because rules can specify specific sizes
        foreach ($image_sizes as $size) {
            // If toggle was enabled, get selected automatic watermarks (ONLY selected ones, not all)
            // This ensures media library uploads work with user-selected watermarks
            if ($was_toggle_enabled) {
                // If NO watermarks were selected, don't apply any
                if (empty($selected_watermark_ids)) {
                    // Skip this size - no watermarks selected by user
                    continue;
                }
                
                // Get ALL active watermarks first
                $all_active = WatermarkHelper::getActiveWatermarks();
                
                // Filter only by automatic watermarking behavior
                $automatic_watermarks = array_filter($all_active, function($watermark) {
                    return $watermark['automatic_watermarking'] === '1' || (boolean)$watermark['automatic_watermarking'] === true;
                });
                
                // CRITICAL: Only apply the SELECTED watermarks (user checked specific ones)
                // Normalize selected IDs to integers for comparison
                $selected_ids_int = array_map('intval', $selected_watermark_ids);
                
                
                $automatic_watermarks = array_filter($automatic_watermarks, function($watermark) use ($selected_ids_int) {
                    // CRITICAL: WatermarkHelper returns 'id' (lowercase), not 'ID'
                    $watermark_id = isset($watermark['id']) ? (int)$watermark['id'] : (isset($watermark['ID']) ? (int)$watermark['ID'] : 0);
                    
                    // Only apply if this watermark ID is in the selected list
                    return in_array($watermark_id, $selected_ids_int, true);
                });
                
                
                // Filter by legacy size rules ONLY if no unified rules exist
                $size_filtered = array_filter($automatic_watermarks, function($watermark) use ($size) {
                    // Check if watermark has unified rules with conditions
                    $rules = $watermark['watermark_rules'] ?? [];
                    $has_unified_conditions = false;
                    if (!empty($rules) && is_array($rules)) {
                        foreach ($rules as $rule) {
                            if (!empty($rule['conditions']) && is_array($rule['conditions'])) {
                                $has_unified_conditions = true;
                                break;
                            }
                        }
                    }
                    
                    // If has unified rules, skip legacy size filter (unified rules will handle it)
                    if ($has_unified_conditions) {
                        $passes_size = true;
                    } else {
                        // Otherwise apply legacy size filter
                        $passes_size = WatermarkHelper::shouldApplyWatermarkByImageSize($watermark, $size);
                    }
                    
                    return $passes_size;
                });
                
                $automatic_watermarks = $size_filtered;
            } else {
                // Normal rule-based filtering (for REST API uploads, etc.)
                // CRITICAL: Always pass attachment_id - shouldApplyWatermarkByPostType will look up parent from metadata
                // But we need to ensure _ulwm_uploaded_to_post_id is set before calling getActiveAutomaticWatermarks
                
                // Get all active automatic watermarks first (without filtering by post type yet)
                $all_active = WatermarkHelper::getActiveWatermarks();
                $automatic_watermarks = array_filter($all_active, function($watermark) {
                    return $watermark['automatic_watermarking'] === '1' || (boolean)$watermark['automatic_watermarking'] === true;
                });
                
                // Now filter by rules: post type AND image size
                // CRITICAL: When was_toggle_enabled is false, we MUST filter by post type rules
                // We already determined in processAfterMetadataGeneration that watermarks should be applied,
                // but we need to filter which specific watermarks match the parent post type
                if (!$was_toggle_enabled && $parent_post_id && $parent_post_id > 0) {
                    $parent_post = get_post($parent_post_id);
                    if ($parent_post) {
                        $parent_post_type = $parent_post->post_type;
                        
                        // Filter by post type rules - manually check each watermark against parent post type
                        // This ensures we don't rely on shouldApplyWatermarkByPostType which checks 'attachment' first
                        // We use the EXACT same logic as in processAfterMetadataGeneration for consistency
                        $automatic_watermarks = array_filter($automatic_watermarks, function($watermark) use ($parent_post_type) {
                            $watermark_on = $watermark['watermark_on'] ?? 'everywhere';
                            
                            // If watermark applies everywhere, include it
                            if ($watermark_on === 'everywhere' || $watermark_on === '') {
                                return true;
                            }
                            
                            // If set to selected_post_types, check if this post type is in the list
                            if ($watermark_on === 'selected_post_types') {
                                $allowed_post_types = $watermark['watermark_post_types'] ?? [];
                                
                                // Fix double-serialized data
                                if (is_string($allowed_post_types)) {
                                    $allowed_post_types = maybe_unserialize($allowed_post_types);
                                    if (is_string($allowed_post_types)) {
                                        $allowed_post_types = maybe_unserialize($allowed_post_types);
                                    }
                                }
                                
                                if (!is_array($allowed_post_types)) {
                                    $allowed_post_types = [];
                                }
                                
                                $allowed_post_types = array_map('strval', array_values($allowed_post_types));
                                
                                // Check if parent post type is in allowed types
                                // CRITICAL: For uploads from page/post editor, we check parent post type ONLY
                                // Do NOT check 'attachment' - that's only for media library uploads
                                return in_array(strval($parent_post_type), $allowed_post_types, true);
                            }
                            
                            // Default: exclude if rules don't match
                            return false;
                        });
                    }
                } elseif (!$was_toggle_enabled) {
                    // No parent post (media library upload without toggle), use standard filtering
                    // This checks if 'attachment' is in allowed types
                    $automatic_watermarks = array_filter($automatic_watermarks, function($watermark) use ($attachment_id) {
                        return WatermarkHelper::shouldApplyWatermarkByPostType($watermark, 'upload', $attachment_id);
                    });
                }
                // If was_toggle_enabled is true, skip post type filtering (already handled above)
                
                // Filter by legacy image size rules ONLY if no unified rules exist
                // If watermark has unified rules with conditions, skip legacy filtering
                $automatic_watermarks = array_filter($automatic_watermarks, function($watermark) use ($size) {
                    $rules = $watermark['watermark_rules'] ?? [];
                    $has_unified_conditions = false;
                    if (!empty($rules) && is_array($rules)) {
                        foreach ($rules as $rule) {
                            if (!empty($rule['conditions']) && is_array($rule['conditions'])) {
                                $has_unified_conditions = true;
                                break;
                            }
                        }
                    }
                    // If has unified rules, skip legacy size filter (unified rules will handle it)
                    if ($has_unified_conditions) {
                        return true;
                    }
                    // Otherwise apply legacy size filter
                    return WatermarkHelper::shouldApplyWatermarkByImageSize($watermark, $size);
                });
            }
            
            // Final filter: evaluate unified watermark_rules conditions (only when present).
            // If a watermark has no unified conditions, it should still be eligible via legacy rules.
            $automatic_watermarks = array_filter($automatic_watermarks, function($watermark) use ($attachment_id, $size, $parent_post_id) {
                $rules = $watermark['watermark_rules'] ?? [];

                $has_conditions = false;
                if (!empty($rules) && is_array($rules)) {
                    foreach ($rules as $rule) {
                        if (!empty($rule['conditions']) && is_array($rule['conditions'])) {
                            $has_conditions = true;
                            break;
                        }
                    }
                }

                // No unified conditions => do not filter out here (legacy filtering already happened above)
                if (!$has_conditions) {
                    return true;
                }

                $eval_context = \MantraBrain\UltimateWatermark\Utils\RulesEvaluator::buildContext(
                    $attachment_id,
                    $size,
                    intval($parent_post_id ?: 0)
                );

                return \MantraBrain\UltimateWatermark\Utils\RulesEvaluator::evaluate($rules, $eval_context);
            });
            
            if (empty($automatic_watermarks)) {
                continue; // No watermarks for this size, skip
            }
            
            // Apply each watermark that passed the rules for this size
            foreach ($automatic_watermarks as $watermark) {
                $this->applyWatermarkToAttachmentSize($attachment_id, $watermark, $size);
            }
        }
    }

    /**
     * Apply watermark to a specific attachment size
     */
    private function applyWatermarkToAttachmentSize(int $attachment_id, array $watermark, string $size): void
    {
        // Get watermark ID - CRITICAL: WatermarkHelper returns 'id' (lowercase), check that first
        $watermark_id = isset($watermark['id']) ? (int)$watermark['id'] : (isset($watermark['ID']) ? (int)$watermark['ID'] : 0);
        
        if (!$watermark_id) {
            return;
        }
        
        // Get the image path for the specific size
        $image_path = $this->getImagePathForSize($attachment_id, $size);
        
        if (!$image_path || !file_exists($image_path)) {
            // If full size might be a scaled image in WordPress, try resolving the scaled variant
            if ($size === 'full') {
                $scaled_path = $this->getScaledImagePathIfExists($attachment_id);
                if ($scaled_path && file_exists($scaled_path)) {
                    $image_path = $scaled_path;
                } else {
                    // Try WebP/AVIF variants if original not present
                    $variant = $this->findExistingVariantPathForAttachment($attachment_id, $size);
                    if ($variant) {
                        $image_path = $variant;
                    } else {
                        return;
                    }
                }
            } else {
                // Try WebP/AVIF variants if original not present
                $variant = $this->findExistingVariantPathForAttachment($attachment_id, $size);
                if ($variant) {
                    $image_path = $variant;
                } else {
                    return;
                }
            }
        }
        
        // Apply watermark using WatermarkService
        try {
            
            // Set attachment context for Pro plugin placeholder resolution
            \MantraBrain\UltimateWatermark\Watermark\WatermarkService::setAttachmentContext([
                'attachment_id' => $attachment_id,
                '_source_image_path' => $image_path,
            ]);
            $success = \MantraBrain\UltimateWatermark\Watermark\WatermarkService::applyWatermarkById($image_path, $watermark_id, $image_path);

            if (!$success && defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Ultimate Watermark: applyWatermarkById failed for watermark ID ' . $watermark_id . ' on attachment ' . $attachment_id);
            }

            if ($success) {
                // Also apply to alternative scaled/original counterpart without double-counting
                if ($size === 'full') {
                    $altPath = $this->getAlternateFullImagePath($attachment_id, $image_path);
                    if ($altPath && file_exists($altPath)) {
                        // Best-effort apply; ignore result for counting to avoid double increments
                        \MantraBrain\UltimateWatermark\Watermark\WatermarkService::applyWatermarkById($altPath, $watermark_id, $altPath);
                    }
                }
                
                // Apply to ALL format variants (both original formats AND WebP/AVIF)
                // This ensures watermark is visible regardless of which format WordPress serves
                $format_variants = $this->getAllFormatVariants($image_path, $attachment_id);
                foreach ($format_variants as $variantPath) {
                    if (file_exists($variantPath) && is_writable($variantPath)) {
                        \MantraBrain\UltimateWatermark\Watermark\WatermarkService::applyWatermarkById($variantPath, $watermark_id, $variantPath);
                    }
                }
                
                // Track watermark usage for this specific size (count once)
                \MantraBrain\UltimateWatermark\Utils\WatermarkUsageTracker::incrementUsage($watermark_id, $attachment_id, $size);
            }
        } catch (\Exception $e) {
            // Silently handle exceptions
        } finally {
            // Cleared only now so the scaled/original copy and WebP/AVIF variants
            // resolve the same attachment (same font size and placeholders).
            \MantraBrain\UltimateWatermark\Watermark\WatermarkService::setAttachmentContext([]);
        }
    }

    /**
     * Get image file path for a specific size
     * Prefers original formats (jpg, png) over WebP/AVIF variants
     */
    private function getImagePathForSize(int $attachment_id, string $size): ?string
    {
        if ($size === 'full') {
            // First, try to get the primary attached file
            $file_path = get_attached_file($attachment_id);
            
            // Always the file WordPress records for this attachment: a same-named
            // .jpg/.png next to a .webp is usually a different attachment.
            if ($file_path && file_exists($file_path)) {
                return $file_path;
            }
            
            // Also check for scaled versions (WordPress 5.3+ big image feature)
            $metadata = wp_get_attachment_metadata($attachment_id);
            if (!empty($metadata['file'])) {
                $upload_dir = wp_upload_dir();
                $scaled_path = $upload_dir['basedir'] . '/' . dirname($metadata['file']) . '/' . basename($metadata['file']);
                if (file_exists($scaled_path)) {
                    return $scaled_path;
                }
                
                // Check for original_image if exists
                if (!empty($metadata['original_image'])) {
                    $original_path = $upload_dir['basedir'] . '/' . dirname($metadata['file']) . '/' . $metadata['original_image'];
                    if (file_exists($original_path)) {
                        return $original_path;
                    }
                }
            }
            return null;
        }
        
        // Try image_get_intermediate_size first (works for registered size names)
        $image_path = image_get_intermediate_size($attachment_id, $size);
        if ($image_path && isset($image_path['path'])) {
            $upload_dir = wp_upload_dir();
            $full_path = $upload_dir['basedir'] . '/' . $image_path['path'];
            
            
            return $full_path;
        }
        
        // Fallback: Check metadata directly for dimension-based sizes (e.g., 2048x2048, 1536x1536)
        // These are not registered WordPress sizes but exist in the metadata 'sizes' array.
        $metadata = wp_get_attachment_metadata($attachment_id);

        if (!empty($metadata['sizes'][$size]['file'])) {
            $upload_dir = wp_upload_dir();
            $base_dir = dirname($metadata['file']);
            $full_path = $upload_dir['basedir'] . '/' . $base_dir . '/' . $metadata['sizes'][$size]['file'];

            if (file_exists($full_path)) {
                return $full_path;
            }
        }
        
        return null;
    }

    /**
     * Get scaled image path if exists (WordPress 5.3+ big image feature)
     */
    private function getScaledImagePathIfExists(int $attachment_id): ?string
    {
        $metadata = wp_get_attachment_metadata($attachment_id);
        if (empty($metadata['file'])) {
            return null;
        }
        $upload_dir = wp_upload_dir();
        $base_dir = $upload_dir['basedir'] . '/' . dirname($metadata['file']);
        $original_file = basename($metadata['file']);
        
        // Check for scaled version (e.g., image-scaled-2048x1536.jpg)
        $scaled_pattern = $base_dir . '/' . pathinfo($original_file, PATHINFO_FILENAME) . '-scaled.' . pathinfo($original_file, PATHINFO_EXTENSION);
        if (file_exists($scaled_pattern)) {
            return $scaled_pattern;
        }
        
        // Also check original_image if exists
        if (!empty($metadata['original_image'])) {
            $original_path = $base_dir . '/' . $metadata['original_image'];
            if (file_exists($original_path)) {
                return $original_path;
            }
        }
        return null;
    }

    /**
     * Get alternate full image path (scaled/original counterpart)
     */
    private function getAlternateFullImagePath(int $attachment_id, string $currentFullPath): ?string
    {
        $metadata = wp_get_attachment_metadata($attachment_id);
        if (empty($metadata['file'])) {
            return null;
        }

        $basedir   = trailingslashit(wp_upload_dir()['basedir']);
        $scaled    = $basedir . $metadata['file'];
        $original  = !empty($metadata['original_image'])
            ? $basedir . dirname($metadata['file']) . '/' . $metadata['original_image']
            : null;
        $current   = realpath($currentFullPath);

        // Big-image uploads: the attached file is "-scaled"; its counterpart is the original.
        if ($current !== false && $current === realpath($scaled)) {
            return ($original && file_exists($original) && realpath($original) !== $current) ? $original : null;
        }

        // Current path is the original: counterpart is the scaled attached file.
        if ($original && $current !== false && $current === realpath($original) && file_exists($scaled)) {
            return $scaled;
        }

        return null;
    }

    /**
     * Get alternative format paths (WebP, AVIF variants)
     */
    private function getAlternativeFormatPaths(string $path): array
    {
        $candidates = [];
        $info = pathinfo($path);
        if (empty($info['dirname']) || empty($info['filename'])) {
            return $candidates;
        }
        $base = $info['dirname'] . '/' . $info['filename'];
        $extensions = ['webp', 'avif'];
        foreach ($extensions as $ext) {
            $alt = $base . '.' . $ext;
            if (strtolower($info['extension'] ?? '') !== $ext && file_exists($alt)) {
                $candidates[] = $alt;
            }
        }
        return $candidates;
    }

    /**
     * Get ALL format variants (both original formats and WebP/AVIF)
     * This ensures watermarks are applied to all formats WordPress might serve
     */
    private function getAllFormatVariants(string $path, int $attachment_id = 0): array
    {
        $candidates = [];
        $info = pathinfo($path);
        if (empty($info['dirname']) || empty($info['filename'])) {
            return $candidates;
        }
        $base = $info['dirname'] . '/' . $info['filename'];
        $current_ext = strtolower($info['extension'] ?? '');
        
        // Only next-gen copies (written by image optimizers next to the
        // original) are siblings of this file. A same-named .jpg/.png/.gif is
        // usually a different attachment and must not be touched.
        $all_formats = ['webp', 'avif'];
        
        foreach ($all_formats as $ext) {
            if ($ext !== $current_ext) {
                $variant = $base . '.' . $ext;
                if (file_exists($variant) && !WatermarkHelper::isFileOfAnotherAttachment($variant, $attachment_id)) {
                    $candidates[] = $variant;
                }
            }
        }
        
        return $candidates;
    }

    /**
     * Find existing variant path for an attachment/size if the canonical file is missing
     */
    private function findExistingVariantPathForAttachment(int $attachment_id, string $size): ?string
    {
        $primary = $this->getImagePathForSize($attachment_id, $size);
        if ($primary && file_exists($primary)) {
            return $primary;
        }
        if ($size === 'full') {
            $alt = $this->getScaledImagePathIfExists($attachment_id);
            if ($alt && file_exists($alt)) {
                return $alt;
            }
        }
        // Try sibling alternative formats based on where the canonical would be
        if ($primary) {
            foreach ($this->getAlternativeFormatPaths($primary) as $variant) {
                if (file_exists($variant) && !WatermarkHelper::isFileOfAnotherAttachment($variant, $attachment_id)) {
                    return $variant;
                }
            }
        }
        return null;
    }
}

