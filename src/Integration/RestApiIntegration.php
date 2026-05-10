<?php

namespace MantraBrain\UltimateWatermark\Integration;

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
     * Initialize the REST API integration
     */
    public function init(): void
    {
        // Hook into add_attachment (fires for ALL attachment creation, including REST API)
        add_action('add_attachment', [$this, 'markForWatermarking']);

        // CRITICAL: Capture watermark toggle state BEFORE the raw request body is consumed
        // by WordPress REST API file upload handler. This ensures we can detect the toggle
        // state even when the raw input has been consumed by the time other hooks fire.
        add_filter('rest_request_before_callbacks', [$this, 'captureWatermarkToggleFromRequest'], 1, 3);

        // Hook into REST API attachment creation (for page/post editor uploads)
        add_action('rest_insert_attachment', [$this, 'handleRestApiAttachmentUpload'], 10, 3);
        add_action('rest_after_insert_attachment', [$this, 'handleRestApiAttachmentUpload'], 10, 3);

        // CRITICAL: Hook into REST API when a product/post is updated to detect featured image changes.
        // Handles both /wp/v2/ and /wc/v3/ REST API routes.
        add_filter('rest_request_before_callbacks', [$this, 'handleFeaturedImageChangeViaRest'], 15, 3);

        // CRITICAL: Hook into admin-ajax set-post-thumbnail action (classic editor / media popup flow).
        // When user clicks "Set as featured image" in the media popup, WordPress fires this AJAX action.
        add_action('ajax_action_set-post-thumbnail', [$this, 'handleFeaturedImageChangeViaAjax'], 20);

        // CRITICAL: Universal hook - set_post_thumbnail fires for ALL featured image assignments
        // regardless of how they are set (REST API, admin-ajax, programmatic, etc.)
        add_action('set_post_thumbnail', [$this, 'handleSetPostThumbnail'], 20, 2);

        // CRITICAL: WooCommerce sometimes sets _thumbnail_id directly via update_post_meta()
        // without calling set_post_thumbnail(), so we need to hook into updated_postmeta
        // to catch those cases as well.
        add_action('updated_post_meta', [$this, 'handleThumbnailMetaUpdate'], 20, 4);

        // CRITICAL: WooCommerce product gallery images are stored in _product_gallery meta
        // (an array of attachment IDs). We need to hook into this to apply watermarks
        // to gallery images as well.
        add_action('added_post_meta', [$this, 'handleGalleryMetaUpdate'], 20, 4);
        add_action('updated_post_meta', [$this, 'handleGalleryMetaUpdate'], 20, 4);

        // Hook into wp_insert_post for attachments as ultimate fallback
        // This fires for ALL post creation including REST API
        add_action('wp_insert_post', [$this, 'handleAttachmentPostInsert'], 10, 3);

        // Hook into metadata generation to apply watermarks after thumbnails are created
        add_filter('wp_generate_attachment_metadata', [$this, 'processAfterMetadataGeneration'], 10, 2);
    }

    /**
     * Handle featured image changes via REST API.
     *
     * When a user uploads an image via the media popup (e.g., WooCommerce featured image),
     * the attachment is created first without a parent post. Then WordPress updates the
     * parent post (product/page/post) to set the _thumbnail_id meta. This hook intercepts
     * that update to detect newly assigned featured images and apply watermarks based on
     * the parent post type rules.
     *
     * NOTE: The rest_request_before_callbacks filter can be called with different argument
     * types depending on context. WooCommerce's rest_preload_api_request passes an array
     * as the second argument instead of WP_REST_Request. We handle this gracefully.
     *
     * @param WP_REST_Response|WP_Error|null $response The response object.
     * @param WP_REST_Request|array          $request  The request object or array (from preload).
     * @param WP_REST_Server|null            $server   The server object.
     * @return WP_REST_Response|WP_Error|null
     */
    public function handleFeaturedImageChangeViaRest($response, $request, $server)
    {
        // CRITICAL: Some contexts (like WooCommerce rest_preload_api_request) pass
        // an array as the second argument instead of WP_REST_Request. Return early.
        if (!$request instanceof \WP_REST_Request) {
            return $response;
        }

        // Only process POST/PUT requests to post endpoints (products, posts, pages, etc.)
        $route = $request->get_route();
        if (strpos($route, '/wp/v2/') === false) {
            return $response;
        }

        $method = strtoupper($request->get_method());
        if (!in_array($method, ['POST', 'PUT', 'PATCH'], true)) {
            return $response;
        }

        // Check if the request includes a featured_media change
        $featuredMedia = $request->get_param('featured_media');
        if ($featuredMedia === null || $featuredMedia === 0 || !is_numeric($featuredMedia)) {
            return $response;
        }

        $attachmentId = absint($featuredMedia);

        // Verify it's a valid image attachment
        if (!wp_attachment_is_image($attachmentId)) {
            return $response;
        }

        // Check if this attachment has already been watermarked (skip if yes)
        $alreadyWatermarked = get_post_meta($attachmentId, '_ulwm_watermarked', true);
        if ($alreadyWatermarked) {
            // Still being processed, skip
            return $response;
        }

        // Check if watermark was already applied to this attachment
        $appliedWatermarks = get_post_meta($attachmentId, 'applied_watermarks', true);
        if (!empty($appliedWatermarks)) {
            // Already has watermarks applied, skip
            return $response;
        }

        // Get the parent post ID from the request URL
        $postId = $request->get_param('id');
        if (!$postId || !is_numeric($postId)) {
            return $response;
        }

        $postId = absint($postId);
        $parentPost = get_post($postId);
        if (!$parentPost) {
            return $response;
        }

        // Store the parent post ID for rule-based watermarking
        update_post_meta($attachmentId, '_ulwm_uploaded_to_post_id', $postId);

        // Get all active automatic watermarks
        $allActive = WatermarkHelper::getActiveWatermarks();
        $automaticWatermarks = array_filter($allActive, function ($watermark) {
            return $watermark['automatic_watermarking'] === '1' || (boolean) $watermark['automatic_watermarking'] === true;
        });

        if (empty($automaticWatermarks)) {
            return $response;
        }

        // Filter by post type rules - check if any watermarks match the parent post type
        $parentPostType = $parentPost->post_type;
        $matchingWatermarks = array_filter($automaticWatermarks, function ($watermark) use ($parentPostType) {
            $watermarkOn = $watermark['watermark_on'] ?? 'everywhere';

            if ($watermarkOn === 'everywhere' || $watermarkOn === '') {
                return true;
            }

            if ($watermarkOn === 'selected_post_types') {
                $allowedPostTypes = $watermark['watermark_post_types'] ?? [];

                if (is_string($allowedPostTypes)) {
                    $allowedPostTypes = maybe_unserialize($allowedPostTypes);
                    if (is_string($allowedPostTypes)) {
                        $allowedPostTypes = maybe_unserialize($allowedPostTypes);
                    }
                }

                if (!is_array($allowedPostTypes)) {
                    $allowedPostTypes = [];
                }

                $allowedPostTypes = array_map('strval', array_values($allowedPostTypes));

                return in_array(strval($parentPostType), $allowedPostTypes, true);
            }

            return false;
        });

        if (empty($matchingWatermarks)) {
            return $response;
        }

        // Mark the attachment for watermarking so processAfterMetadataGeneration applies watermarks
        // with the correct parent post type context
        update_post_meta($attachmentId, '_ulwm_watermarked', true);

        // Apply watermarks immediately since metadata has already been generated
        try {
            $this->applyWatermarksForFeaturedImage($attachmentId, $matchingWatermarks, $postId);
        } catch (\Exception $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Ultimate Watermark: Error applying watermark to featured image ' . $attachmentId . ': ' . $e->getMessage());
            }
        }

        return $response;
    }

    /**
     * Apply watermarks to a featured image that was just assigned to a post.
     *
     * This method respects ALL watermark rules: post type rules, image size rules,
     * and watermark_rules conditions (unified rules with image_size, product_cat, etc.).
     *
     * @param int   $attachmentId       The attachment ID.
     * @param array $matchingWatermarks Watermarks that match the parent post type.
     * @param int   $parentPostId       The parent post ID.
     */
    private function applyWatermarksForFeaturedImage(int $attachmentId, array $matchingWatermarks, int $parentPostId): void
    {
        // Get all registered image sizes
        $imageSizes = get_intermediate_image_sizes();
        $imageSizes[] = 'full';

        // Create backup before applying watermarks
        $this->createBackupForWatermarking($attachmentId);

        foreach ($imageSizes as $size) {
            // Apply the FULL rule system for each size
            $watermarksForSize = [];

            foreach ($matchingWatermarks as $watermark) {
                // Step 1: Legacy image size filter (only if no unified rules exist)
                $rules = $watermark['watermark_rules'] ?? [];
                $hasUnifiedConditions = false;

                if (!empty($rules) && is_array($rules)) {
                    foreach ($rules as $rule) {
                        if (!empty($rule['conditions']) && is_array($rule['conditions'])) {
                            $hasUnifiedConditions = true;
                            break;
                        }
                    }
                }

                if (!$hasUnifiedConditions) {
                    // Apply legacy size filter
                    if (!WatermarkHelper::shouldApplyWatermarkByImageSize($watermark, $size)) {
                        continue;
                    }
                }

                // Step 2: Unified watermark_rules conditions
                if ($hasUnifiedConditions) {
                    $evalContext = \MantraBrain\UltimateWatermark\Utils\RulesEvaluator::buildContext(
                        $attachmentId,
                        $size,
                        $parentPostId
                    );

                    if (!\MantraBrain\UltimateWatermark\Utils\RulesEvaluator::evaluate($rules, $evalContext)) {
                        continue;
                    }
                }

                // All rules passed - include this watermark for this size
                $watermarksForSize[] = $watermark;
            }

            if (empty($watermarksForSize)) {
                continue;
            }

            // Apply each watermark for this size
            foreach ($watermarksForSize as $watermark) {
                $this->applyWatermarkToAttachmentSize($attachmentId, $watermark, $size);
            }
        }

        // Clean up the flag
        delete_post_meta($attachmentId, '_ulwm_watermarked');
    }

    /**
     * Handle featured image changes via admin-ajax (classic editor / media popup flow).
     *
     * When user clicks "Set as featured image" in the media popup, WordPress fires
     * the 'set-post-thumbnail' AJAX action. This hook intercepts it to apply watermarks
     * based on the parent post type rules.
     *
     * @return void
     */
    public function handleFeaturedImageChangeViaAjax(): void
    {
        // Only process if we have the required POST data
        if (!isset($_POST['post_id'], $_POST['thumbnail_id'])) {
            return;
        }

        $postId = absint($_POST['post_id']);
        $attachmentId = absint($_POST['thumbnail_id']);

        // Verify it's a valid image attachment
        if (!wp_attachment_is_image($attachmentId)) {
            return;
        }

        // Check if watermark was already applied to this attachment
        $appliedWatermarks = get_post_meta($attachmentId, 'applied_watermarks', true);
        if (!empty($appliedWatermarks)) {
            return; // Already watermarked
        }

        $parentPost = get_post($postId);
        if (!$parentPost) {
            return;
        }

        $this->applyWatermarksForParentPost($attachmentId, $postId);
    }

    /**
     * Handle set_post_thumbnail action (universal fallback for ALL featured image assignments).
     *
     * This hook fires for ALL featured image assignments regardless of how they are set:
     * - REST API (both /wp/v2/ and /wc/v3/)
     * - admin-ajax (classic editor / media popup)
     * - Programmatic (set_post_thumbnail() function)
     *
     * @param int $post_ID The post ID.
     * @param int $thumbnail_ID The attachment ID.
     * @return void
     */
    public function handleSetPostThumbnail(int $post_ID, int $thumbnail_ID): void
    {
        // Verify it's a valid image attachment
        if (!wp_attachment_is_image($thumbnail_ID)) {
            return;
        }

        // Check if watermark was already applied to this attachment
        $appliedWatermarks = get_post_meta($thumbnail_ID, 'applied_watermarks', true);
        if (!empty($appliedWatermarks)) {
            return; // Already watermarked
        }

        // Check if currently being processed by another hook
        $processingFlag = get_post_meta($thumbnail_ID, '_ulwm_processing', true);
        if ($processingFlag) {
            return; // Already being processed
        }

        $parentPost = get_post($post_ID);
        if (!$parentPost) {
            return;
        }

        $this->applyWatermarksForParentPost($thumbnail_ID, $post_ID);
    }

    /**
     * Handle _thumbnail_id meta updates (catches WooCommerce direct meta updates).
     *
     * WooCommerce sometimes sets _thumbnail_id directly via update_post_meta()
     * without calling set_post_thumbnail(), so we need to hook into updated_post_meta
     * to catch those cases as well.
     *
     * @param int    $meta_id    Meta ID.
     * @param int    $post_id    Post ID.
     * @param string $meta_key   Meta key.
     * @param mixed  $meta_value Meta value.
     * @return void
     */
    public function handleThumbnailMetaUpdate(int $meta_id, int $post_id, string $meta_key, $meta_value): void
    {
        // Only process _thumbnail_id meta updates
        if ($meta_key !== '_thumbnail_id') {
            return;
        }

        // Convert to int (meta_value might be string from POST data)
        $thumbnailId = absint($meta_value);
        if ($thumbnailId <= 0) {
            return;
        }

        // Verify it's a valid image attachment
        if (!wp_attachment_is_image($thumbnailId)) {
            return;
        }

        // Check if watermark was already applied to this attachment
        $appliedWatermarks = get_post_meta($thumbnailId, 'applied_watermarks', true);
        if (!empty($appliedWatermarks)) {
            return; // Already watermarked
        }

        // Check if currently being processed by another hook
        $processingFlag = get_post_meta($thumbnailId, '_ulwm_processing', true);
        if ($processingFlag) {
            return; // Already being processed
        }

        $parentPost = get_post($post_id);
        if (!$parentPost) {
            return;
        }

        $this->applyWatermarksForParentPost($thumbnailId, $post_id);
    }

    /**
     * Handle WooCommerce product gallery meta updates (_product_gallery).
     *
     * WooCommerce stores product gallery images as an array of attachment IDs
     * in the _product_gallery meta field. When this meta is updated, we need to
     * apply watermarks to any new images that were added to the gallery.
     *
     * @param int    $meta_id    Meta ID.
     * @param int    $post_id    Post ID.
     * @param string $meta_key   Meta key.
     * @param mixed  $meta_value Meta value.
     * @return void
     */
    public function handleGalleryMetaUpdate(int $meta_id, int $post_id, string $meta_key, $meta_value): void
    {
        // Only process _product_gallery meta updates
        if ($meta_key !== '_product_gallery') {
            return;
        }

        // The meta_value is an array of attachment IDs (serialized)
        if (!is_array($meta_value)) {
            $meta_value = maybe_unserialize($meta_value);
        }

        if (!is_array($meta_value) || empty($meta_value)) {
            return;
        }

        $parentPost = get_post($post_id);
        if (!$parentPost || $parentPost->post_type !== 'product') {
            return;
        }

        // Process each gallery image
        foreach ($meta_value as $attachmentId) {
            $attachmentId = absint($attachmentId);
            if ($attachmentId <= 0) {
                continue;
            }

            // Verify it's a valid image attachment
            if (!wp_attachment_is_image($attachmentId)) {
                continue;
            }

            // Check if watermark was already applied to this attachment
            $appliedWatermarks = get_post_meta($attachmentId, 'applied_watermarks', true);
            if (!empty($appliedWatermarks)) {
                continue; // Already watermarked
            }

            // Check if currently being processed by another hook
            $processingFlag = get_post_meta($attachmentId, '_ulwm_processing', true);
            if ($processingFlag) {
                continue; // Already being processed
            }

            $this->applyWatermarksForParentPost($attachmentId, $post_id);
        }
    }

    /**
     * Apply watermarks to an image based on its parent post type.
     *
     * This is the shared method used by handleFeaturedImageChangeViaAjax and handleSetPostThumbnail.
     * It respects ALL watermark rules: post type rules, image size rules,
     * and watermark_rules conditions (unified rules with image_size, product_cat, etc.).
     *
     * @param int $attachmentId The attachment ID.
     * @param int $parentPostId The parent post ID.
     * @return void
     */
    private function applyWatermarksForParentPost(int $attachmentId, int $parentPostId): void
    {
        update_post_meta($attachmentId, '_ulwm_processing', true);

        try {
            $parentPost = get_post($parentPostId);
            if (!$parentPost) {
                return;
            }

            update_post_meta($attachmentId, '_ulwm_uploaded_to_post_id', $parentPostId);

            $allActive = WatermarkHelper::getActiveWatermarks();

            $automaticWatermarks = array_filter($allActive, function ($watermark) {
                return $watermark['automatic_watermarking'] === '1' || (boolean) $watermark['automatic_watermarking'] === true;
            });

            if (empty($automaticWatermarks)) {
                return;
            }

            // Filter by post type rules — keep watermarks where the parent
            // post type is allowed (or where the watermark applies "everywhere").
            $parentPostType = $parentPost->post_type;
            $matchingWatermarks = array_filter($automaticWatermarks, function ($watermark) use ($parentPostType) {
                $watermarkOn = $watermark['watermark_on'] ?? 'everywhere';

                if ($watermarkOn === 'everywhere' || $watermarkOn === '') {
                    return true;
                }

                if ($watermarkOn === 'selected_post_types') {
                    $allowedPostTypes = $watermark['watermark_post_types'] ?? [];
                    if (is_string($allowedPostTypes)) {
                        $allowedPostTypes = maybe_unserialize($allowedPostTypes);
                        if (is_string($allowedPostTypes)) {
                            $allowedPostTypes = maybe_unserialize($allowedPostTypes);
                        }
                    }
                    if (!is_array($allowedPostTypes)) {
                        $allowedPostTypes = [];
                    }
                    $allowedPostTypes = array_map('strval', array_values($allowedPostTypes));

                    return in_array(strval($parentPostType), $allowedPostTypes, true);
                }

                return false;
            });

            if (empty($matchingWatermarks)) {
                return;
            }

            $this->applyWatermarksForFeaturedImage($attachmentId, $matchingWatermarks, $parentPostId);

        } finally {
            delete_post_meta($attachmentId, '_ulwm_processing');
        }
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
     * NOTE: The rest_request_before_callbacks filter can be called with different argument
     * types depending on context. WooCommerce's rest_preload_api_request passes an array
     * as the second argument instead of WP_REST_Request. We handle this gracefully.
     *
     * @param WP_REST_Response|WP_Error|null $response The response object.
     * @param WP_REST_Request|array          $request  The request object or array (from preload).
     * @param WP_REST_Server|null            $server   The server object.
     * @return WP_REST_Response|WP_Error|null
     */
    public function captureWatermarkToggleFromRequest($response, $request, $server)
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
        if (!$creating || !wp_attachment_is_image($attachment->ID)) {
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
            $option_value = get_option('ultimate_watermark_auto_apply_toggle', '0');
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
            elseif (get_option('ultimate_watermark_temp_selected_ids', '') !== '') {
                $ids_string = sanitize_text_field(get_option('ultimate_watermark_temp_selected_ids', ''));
                // Clear after reading (one-time use)
                delete_option('ultimate_watermark_temp_selected_ids');
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
        
        // Check if this image should be watermarked
        // _ulwm_watermarked flag is ONLY set by toggle (media library uploads with toggle ON)
        // For REST API uploads, we check rules directly without setting this flag
        $was_toggle_enabled = get_post_meta($attachment_id, '_ulwm_watermarked', true);
        
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
            try {
                // Increase memory limit for watermark processing
                $original_memory_limit = ini_get('memory_limit');
                ini_set('memory_limit', '256M');
                
                // Create backup BEFORE applying watermarks
                $this->createBackupForWatermarking($attachment_id);
                
                // Apply watermarks to the generated thumbnails
                // Pass whether toggle was enabled (affects filtering behavior)
                // If toggle was enabled, apply all; if rule-based, filter by rules
                $this->applyWatermarksToGeneratedImages($attachment_id, $was_toggle_enabled);
                
                // Restore original memory limit
                ini_set('memory_limit', $original_memory_limit);
                
                // Remove the flag to prevent re-processing (only if toggle was enabled)
                if ($was_toggle_enabled) {
                    delete_post_meta($attachment_id, '_ulwm_watermarked');
                    delete_post_meta($attachment_id, '_ulwm_selected_watermark_ids');
                    // DON'T reset the toggle option - let localStorage control it instead
                    // This allows toggle state to persist across page reloads
                    // update_option('ultimate_watermark_auto_apply_toggle', '0');
                }
                
            } catch (\Exception $e) {
                // Restore original memory limit
                ini_set('memory_limit', $original_memory_limit);
            }
        }
        
        return $metadata;
    }

    /**
     * Create backup for watermarking
     */
    private function createBackupForWatermarking(int $attachment_id): void
    {
        // Get the original image file path using get_attached_file
        // This should be the original unwatermarked file at this point
        $original_path = get_attached_file($attachment_id);
        if (!$original_path || !file_exists($original_path)) {
            return;
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
        
        // Create backup with all sizes
        \MantraBrain\UltimateWatermark\Utils\BackupManager::createBackup(
            $backup_paths['full'],
            $attachment_id,
            0,
            $backup_paths
        );
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
            // Clear context after use
            \MantraBrain\UltimateWatermark\Watermark\WatermarkService::setAttachmentContext([]);

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
                $format_variants = $this->getAllFormatVariants($image_path);
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
            
            // Prefer original formats (jpg, png) over WebP/AVIF
            if ($file_path) {
                $ext = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
                // If it's a WebP/AVIF, try to find the original format
                if (in_array($ext, ['webp', 'avif'])) {
                    $base = pathinfo($file_path, PATHINFO_DIRNAME) . '/' . pathinfo($file_path, PATHINFO_FILENAME);
                    // Try common original formats
                    foreach (['jpg', 'jpeg', 'png'] as $original_ext) {
                        $original_path = $base . '.' . $original_ext;
                        if (file_exists($original_path)) {
                            return $original_path;
                        }
                    }
                }
                
                // If original format found or not WebP/AVIF, return it
                if (file_exists($file_path)) {
                    return $file_path;
                }
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
            
            // Prefer original formats over WebP/AVIF
            $ext = strtolower(pathinfo($full_path, PATHINFO_EXTENSION));
            if (in_array($ext, ['webp', 'avif'])) {
                $base = pathinfo($full_path, PATHINFO_DIRNAME) . '/' . pathinfo($full_path, PATHINFO_FILENAME);
                // Try common original formats
                foreach (['jpg', 'jpeg', 'png'] as $original_ext) {
                    $original_path = $base . '.' . $original_ext;
                    if (file_exists($original_path)) {
                        return $original_path;
                    }
                }
            }
            
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
                // Prefer original formats over WebP/AVIF
                $ext = strtolower(pathinfo($full_path, PATHINFO_EXTENSION));
                if (in_array($ext, ['webp', 'avif'])) {
                    $base = pathinfo($full_path, PATHINFO_DIRNAME) . '/' . pathinfo($full_path, PATHINFO_FILENAME);
                    foreach (['jpg', 'jpeg', 'png'] as $original_ext) {
                        $original_path = $base . '.' . $original_ext;
                        if (file_exists($original_path)) {
                            return $original_path;
                        }
                    }
                }
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
        $upload_dir = wp_upload_dir();
        $base_dir = $upload_dir['basedir'] . '/' . dirname($metadata['file']);
        $original_file = basename($metadata['file']);
        
        // If current path is scaled, try original
        if (strpos($currentFullPath, '-scaled.') !== false) {
            $original_path = $base_dir . '/' . $original_file;
            if (file_exists($original_path)) {
                return $original_path;
            }
            if (!empty($metadata['original_image'])) {
                $metaFile = $base_dir . '/' . $metadata['original_image'];
                if (file_exists($metaFile)) {
                    return $metaFile;
                }
            }
        }
        
        // If current path is original, try scaled
        $scaled_pattern = $base_dir . '/' . pathinfo($original_file, PATHINFO_FILENAME) . '-scaled.' . pathinfo($original_file, PATHINFO_EXTENSION);
        if (file_exists($scaled_pattern)) {
            return $scaled_pattern;
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
    private function getAllFormatVariants(string $path): array
    {
        $candidates = [];
        $info = pathinfo($path);
        if (empty($info['dirname']) || empty($info['filename'])) {
            return $candidates;
        }
        $base = $info['dirname'] . '/' . $info['filename'];
        $current_ext = strtolower($info['extension'] ?? '');
        
        // All possible formats
        $all_formats = ['jpg', 'jpeg', 'png', 'webp', 'avif', 'gif'];
        
        foreach ($all_formats as $ext) {
            if ($ext !== $current_ext) {
                $variant = $base . '.' . $ext;
                if (file_exists($variant)) {
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
                if (file_exists($variant)) {
                    return $variant;
                }
            }
        }
        return null;
    }
}

