<?php

namespace MantraBrain\UltimateWatermark\Utils;

/**
 * Attachment Guard
 *
 * Decides whether an attachment must never be watermarked or served through a
 * watermarked proxy. The main case is a file sold as a digital download
 * (WooCommerce, Dokan, Easy Digital Downloads, Download Monitor): the original
 * must stay byte-for-byte untouched and must never be copied to a public URL.
 *
 * An attachment is protected when any of these is true:
 *  - it was uploaded through a "downloadable product" uploader
 *    (WooCommerce and Dokan send `type=downloadable_product`);
 *  - its file lives inside a protected download directory
 *    (e.g. uploads/woocommerce_uploads);
 *  - its URL is referenced by a product's `_downloadable_files` meta;
 *  - the `ultimate_watermark_skip_attachment` filter says so.
 *
 * @package UltimateWatermark
 * @since 2.1.5
 */
class AttachmentGuard
{
    /**
     * Meta flag written on attachments uploaded as downloadable product files.
     */
    public const META_PROTECTED = '_ulwm_protected_download';

    /**
     * Per-request decision cache, keyed by attachment ID.
     *
     * @var array<int, bool>
     */
    private static array $cache = [];

    /**
     * Register hooks.
     */
    public static function init(): void
    {
        // Priority 1 so the flag exists before any other add_attachment logic runs.
        add_action('add_attachment', [self::class, 'flagDownloadUpload'], 1);

        // Sold files must reach the buyer exactly as uploaded: no "-scaled"
        // downsized copy (WooCommerce/Dokan would link that instead of the
        // original) and no thumbnail copies of the paid file.
        add_filter('big_image_size_threshold', [self::class, 'keepDownloadOriginalSize'], 999);
        add_filter('intermediate_image_sizes_advanced', [self::class, 'skipDownloadSubsizes'], 999);

        // Admin screens and media modals must not try to display protected
        // files as images: WooCommerce denies direct access to them (403) and
        // they are full-size originals. Show them as files instead.
        add_filter('wp_prepare_attachment_for_js', [self::class, 'presentDownloadAsFile'], 20, 2);
        add_filter('wp_get_attachment_image_src', [self::class, 'downloadIconInAdmin'], 20, 4);
    }

    /**
     * Whether an attachment's file lives in a protected download directory.
     */
    private static function isProtectedFile(int $attachment_id): bool
    {
        return $attachment_id > 0 && self::isInProtectedDirectory((string) get_attached_file($attachment_id, true));
    }

    /**
     * Media modal / grid: show a protected download as a file, not an image.
     *
     * @param array    $response   Attachment data for JS.
     * @param \WP_Post $attachment Attachment.
     * @return array
     */
    public static function presentDownloadAsFile($response, $attachment)
    {
        if (!is_array($response) || !$attachment instanceof \WP_Post || !self::isProtectedFile((int) $attachment->ID)) {
            return $response;
        }

        $response['type'] = 'application';
        $response['subtype'] = 'octet-stream';
        $response['icon'] = wp_mime_type_icon('application/octet-stream');
        unset($response['sizes'], $response['image']);

        return $response;
    }

    /**
     * Admin list tables and edit screens: a file icon instead of the protected image.
     *
     * @param array|false  $image         [url, width, height, is_intermediate] or false.
     * @param int          $attachment_id Attachment ID.
     * @param string|int[] $size          Requested size.
     * @param bool         $icon          Whether an icon was requested.
     * @return array|false
     */
    public static function downloadIconInAdmin($image, $attachment_id, $size, $icon)
    {
        if (!is_array($image) || !is_admin() || !self::isProtectedFile((int) $attachment_id)) {
            return $image;
        }

        $icon_url = wp_mime_type_icon('application/octet-stream');
        return $icon_url ? [$icon_url, 48, 64, false] : $image;
    }

    /**
     * Whether downloadable product uploads should be stored untouched.
     */
    private static function preserveDownloadOriginals(): bool
    {
        return self::isDownloadUploadRequest() && (bool) apply_filters('ultimate_watermark_preserve_download_originals', true);
    }

    /**
     * Disable WordPress' big-image downscaling for downloadable product uploads.
     *
     * @param int|false $threshold Threshold in pixels.
     * @return int|false
     */
    public static function keepDownloadOriginalSize($threshold)
    {
        return self::preserveDownloadOriginals() ? false : $threshold;
    }

    /**
     * Skip thumbnail generation for downloadable product uploads.
     *
     * @param array $sizes Sizes WordPress is about to generate.
     * @return array
     */
    public static function skipDownloadSubsizes($sizes)
    {
        return self::preserveDownloadOriginals() ? [] : $sizes;
    }

    /**
     * Whether the current request is a WooCommerce/Dokan downloadable-file upload.
     */
    public static function isDownloadUploadRequest(): bool
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only classification of the current upload request.
        $type = $_REQUEST['type'] ?? '';
        return is_string($type) && sanitize_key(wp_unslash($type)) === 'downloadable_product';
    }

    /**
     * Mark freshly created attachments that are downloadable product files.
     */
    public static function flagDownloadUpload($attachment_id): void
    {
        $attachment_id = (int) $attachment_id;
        if ($attachment_id <= 0) {
            return;
        }

        $file = get_attached_file($attachment_id);
        if (self::isDownloadUploadRequest() || (is_string($file) && self::isInProtectedDirectory($file))) {
            update_post_meta($attachment_id, self::META_PROTECTED, '1');
            self::$cache[$attachment_id] = true;
        }
    }

    /**
     * Whether a file path lies inside a directory reserved for protected downloads.
     */
    public static function isInProtectedDirectory(string $path): bool
    {
        if ($path === '') {
            return false;
        }

        $path = wp_normalize_path($path);

        /**
         * Directory names (relative to uploads) that hold protected download files.
         *
         * @param string[] $dirs
         */
        $dirs = (array) apply_filters('ultimate_watermark_protected_directories', [
            'woocommerce_uploads', // WooCommerce + Dokan
            'edd',                 // Easy Digital Downloads
            'dlm_uploads',         // Download Monitor
        ]);

        $uploads = wp_upload_dir(null, false);
        $basedir = trailingslashit(wp_normalize_path($uploads['basedir'] ?? ''));

        foreach ($dirs as $dir) {
            $dir = trim(wp_normalize_path((string) $dir), '/');
            if ($dir === '') {
                continue;
            }
            if ($basedir !== '/' && strpos($path, $basedir . $dir . '/') === 0) {
                return true;
            }
            // Fallback for custom upload paths / symlinks.
            if (strpos($path, '/' . $dir . '/') !== false && $dir === 'woocommerce_uploads') {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether the attachment's URL is used as a downloadable file of any product.
     */
    public static function isUsedAsDownloadableFile(int $attachment_id): bool
    {
        global $wpdb;

        $relative = get_post_meta($attachment_id, '_wp_attached_file', true);
        if (!is_string($relative) || $relative === '') {
            return false;
        }

        // _downloadable_files is a serialized array whose 'file' entries are
        // absolute URLs (…/uploads/<relative>). Anchoring on the uploads folder
        // name and the closing quote of the serialized string prevents a
        // featured image at uploads/2026/10/a.jpg from matching a download at
        // uploads/woocommerce_uploads/2026/10/a.jpg.
        $uploads     = wp_upload_dir(null, false);
        $uploads_dir = wp_basename(untrailingslashit($uploads['baseurl'] ?? 'uploads'));
        $needle      = '/' . $uploads_dir . '/' . ltrim($relative, '/') . '"';

        $found = $wpdb->get_var($wpdb->prepare(
            "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_downloadable_files' AND meta_value LIKE %s LIMIT 1",
            '%' . $wpdb->esc_like($needle) . '%'
        ));

        return !empty($found);
    }

    /**
     * Whether watermarking (permanent or on-the-fly) must skip this attachment.
     */
    public static function shouldSkip(int $attachment_id): bool
    {
        if ($attachment_id <= 0) {
            return true;
        }

        if (isset(self::$cache[$attachment_id])) {
            return self::$cache[$attachment_id];
        }

        $skip = get_post_meta($attachment_id, self::META_PROTECTED, true) === '1';

        if (!$skip) {
            $file = get_attached_file($attachment_id, true);
            $skip = is_string($file) && self::isInProtectedDirectory($file);
        }

        if (!$skip) {
            $skip = self::isUsedAsDownloadableFile($attachment_id);
        }

        /**
         * Filter whether an attachment is excluded from all watermarking.
         *
         * @param bool $skip          Current decision.
         * @param int  $attachment_id Attachment ID.
         */
        $skip = (bool) apply_filters('ultimate_watermark_skip_attachment', $skip, $attachment_id);

        self::$cache[$attachment_id] = $skip;
        return $skip;
    }

    /**
     * Drop the cached decision for an attachment (e.g. after its meta changed).
     */
    public static function forget(int $attachment_id): void
    {
        unset(self::$cache[$attachment_id]);
    }
}
