<?php

namespace MantraBrain\UltimateWatermark\Ajax;

use MantraBrain\UltimateWatermark\Watermark\WatermarkService;
use MantraBrain\UltimateWatermark\Watermark\WatermarkProcessorFactory;

/**
 * Watermark Preview AJAX Handler
 *
 * Handles AJAX requests for watermark preview generation and library status.
 * All endpoints require manage_options capability.
 *
 * @package UltimateWatermark
 * @since   2.0.0
 */
class WatermarkPreviewHandler
{
    public function __construct()
    {
        add_action('wp_ajax_ultimate_watermark_generate_preview', [$this, 'handleGeneratePreview']);
        add_action('wp_ajax_ultimate_watermark_get_library_status', [$this, 'handleGetLibraryStatus']);
    }

    /**
     * Generate a live preview from posted form data.
     */
    public function handleGeneratePreview(): void
    {
        $this->verifyAuthorization();

        if (!WatermarkService::isAvailable()) {
            wp_send_json_error([
                'code'    => 'no_image_library',
                'message' => __('No image processing library available. Please install the GD or Imagick PHP extension.', 'ultimate-watermark'),
            ]);
            return;
        }

        $watermarkData    = $this->extractWatermarkData($_POST);
        $sourceImagePath  = $this->getSourceImagePath();

        if (!$sourceImagePath) {
            wp_send_json_error([
                'code'    => 'source_missing',
                'message' => __('Preview source image is missing or unreadable.', 'ultimate-watermark'),
            ]);
            return;
        }

        try {
            $this->cleanupExistingPreviews();

            // Provide an attachment context to the preview pipeline so Pro
            // placeholder substitution ({user_display_name}, {camera_model},
            // {post_title}, etc.) can resolve to real values rather than
            // returning the literal token. We use a "phantom" attachment ID
            // — most-recent image in the library — when the user is editing
            // a watermark template that hasn't been bound to a real upload.
            $context_attachment_id = $this->resolvePreviewAttachmentId($watermarkData);
            if ($context_attachment_id > 0) {
                WatermarkService::setAttachmentContext([
                    'attachment_id' => $context_attachment_id,
                    'is_preview'    => true,
                ]);
            }

            try {
                $previewResult = WatermarkService::generatePreview($sourceImagePath, $watermarkData);
            } finally {
                // Always clear so the preview context can't leak into a real
                // watermark application later in the request lifecycle.
                WatermarkService::setAttachmentContext([]);
            }

            if ($previewResult === false) {
                wp_send_json_error([
                    'code'    => 'preview_failed',
                    'message' => __('Could not generate preview. Check the server log for details.', 'ultimate-watermark'),
                ]);
                return;
            }

            $previewUrl  = $this->normalizePreviewUrl($previewResult);
            $previewPath = $this->urlToPath($previewUrl);

            if (!$previewPath || !file_exists($previewPath)) {
                wp_send_json_error([
                    'code'    => 'preview_missing',
                    'message' => __('Preview was generated but the resulting file could not be located.', 'ultimate-watermark'),
                ]);
                return;
            }

            wp_send_json_success([
                'preview_url'  => $previewUrl,
                'library_used' => WatermarkService::getCurrentLibrary(),
                'generated_at' => current_time('timestamp'),
                'message'      => __('Preview generated successfully.', 'ultimate-watermark'),
            ]);
        } catch (\Throwable $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Ultimate Watermark Preview Error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            }
            wp_send_json_error([
                'code'    => 'exception',
                'message' => sprintf(
                    /* translators: %s: short error message */
                    __('Preview generation failed: %s', 'ultimate-watermark'),
                    $e->getMessage()
                ),
            ]);
        }
    }

    /**
     * Report which image library is in use and what formats it supports.
     */
    public function handleGetLibraryStatus(): void
    {
        $this->verifyAuthorization();

        wp_send_json_success([
            'libraries'         => WatermarkService::getLibraryStatus(),
            'is_available'      => WatermarkService::isAvailable(),
            'current_library'   => WatermarkService::getCurrentLibrary(),
            'supported_formats' => WatermarkProcessorFactory::getSupportedFormats(),
        ]);
    }

    // ---------------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------------

    /**
     * Verify nonce + capabilities. Sends an error response and exits on failure.
     */
    private function verifyAuthorization(): void
    {
        $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';

        if ($nonce === '' || !wp_verify_nonce($nonce, 'ultimate_watermark_ajax')) {
            wp_send_json_error([
                'code'    => 'invalid_nonce',
                'message' => __('Security check failed. Please reload the page and try again.', 'ultimate-watermark'),
            ]);
            exit;
        }

        if (!current_user_can('manage_options')) {
            wp_send_json_error([
                'code'    => 'forbidden',
                'message' => __('You do not have permission to perform this action.', 'ultimate-watermark'),
            ]);
            exit;
        }
    }

    /**
     * Pull the watermark fields out of the POST payload, sanitizing as we go.
     */
    private function extractWatermarkData(array $postData): array
    {
        $reserved = ['nonce', 'action', 'ultimate_watermark_nonce', '_wp_http_referer'];
        $data     = [];

        foreach ($postData as $key => $value) {
            if (in_array($key, $reserved, true)) {
                continue;
            }

            if (is_array($value)) {
                $data[$key] = array_map('sanitize_text_field', wp_unslash($value));
            } else {
                $data[$key] = sanitize_text_field(wp_unslash((string) $value));
            }
        }

        return $data;
    }

    /**
     * Pick an attachment ID to use as the preview's resolution context.
     *
     * Order of preference:
     *   1. An explicit attachment_id field in the form data (used by the
     *      "preview against this image" button if the watermark page ever
     *      offers it).
     *   2. The most recently uploaded image in the media library — gives
     *      EXIF / camera placeholders a real source to read from.
     *   3. Zero — the placeholder substitution will leave the literal
     *      token in place, which is at least visible feedback to the user
     *      that the placeholder will resolve at apply time.
     */
    private function resolvePreviewAttachmentId(array $watermarkData): int
    {
        if (!empty($watermarkData['attachment_id'])) {
            $candidate = (int) $watermarkData['attachment_id'];
            if ($candidate > 0 && get_post_type($candidate) === 'attachment') {
                return $candidate;
            }
        }

        $recent = get_posts([
            'post_type'      => 'attachment',
            'post_mime_type' => 'image',
            'post_status'    => 'inherit',
            'numberposts'    => 1,
            'orderby'        => 'date',
            'order'          => 'DESC',
            'fields'         => 'ids',
        ]);
        return !empty($recent) ? (int) $recent[0] : 0;
    }

    /**
     * Resolve the source image path used for preview rendering.
     *
     * If the bundled preview image is missing we attempt to regenerate it via
     * GD; that should never normally happen, but we guard against it.
     */
    private function getSourceImagePath(): ?string
    {
        $pluginDir   = plugin_dir_path(dirname(__DIR__));
        $previewPath = $pluginDir . 'assets/images/preview-image.jpg';

        if (file_exists($previewPath) && is_readable($previewPath)) {
            return $previewPath;
        }

        $regenerated = $this->createDefaultPreviewImage($previewPath);
        return $regenerated && file_exists($regenerated) ? $regenerated : null;
    }

    /**
     * Generate a placeholder source image if the bundled one is missing.
     */
    private function createDefaultPreviewImage(string $previewImagePath): ?string
    {
        if (!extension_loaded('gd')) {
            return null;
        }

        $dir = dirname($previewImagePath);
        if (!is_dir($dir)) {
            wp_mkdir_p($dir);
        }

        $image = imagecreatetruecolor(800, 533);
        if (!$image) {
            return null;
        }

        $bg     = imagecolorallocate($image, 232, 240, 255);
        $stripe = imagecolorallocate($image, 217, 226, 247);
        $text   = imagecolorallocate($image, 99, 102, 241);

        imagefill($image, 0, 0, $bg);
        for ($y = 0; $y < 533; $y += 24) {
            imagefilledrectangle($image, 0, $y, 800, $y + 1, $stripe);
        }
        imagestring($image, 5, 320, 260, 'Sample preview image', $text);

        $saved = imagejpeg($image, $previewImagePath, 90);
        imagedestroy($image);

        return $saved ? $previewImagePath : null;
    }

    /**
     * Make sure the result we return is always a URL, not a server path.
     */
    private function normalizePreviewUrl(string $previewResult): string
    {
        if (filter_var($previewResult, FILTER_VALIDATE_URL)) {
            return $previewResult;
        }

        $uploadDir = wp_upload_dir();
        $base      = wp_normalize_path($uploadDir['basedir']);
        $normPath  = wp_normalize_path($previewResult);

        if (strpos($normPath, $base) === 0) {
            return $uploadDir['baseurl'] . substr($normPath, strlen($base));
        }

        // Last resort: assume file lives in the watermark preview folder.
        return trailingslashit($uploadDir['baseurl']) . 'ultimate-watermark/' . basename($previewResult);
    }

    /**
     * Convert a preview URL back to its filesystem path so we can verify it.
     */
    private function urlToPath(string $previewUrl): ?string
    {
        $uploadDir = wp_upload_dir();
        $baseUrl   = trailingslashit($uploadDir['baseurl']);

        if (strpos($previewUrl, $uploadDir['baseurl']) === 0) {
            $relative = substr($previewUrl, strlen($uploadDir['baseurl']));
            return $uploadDir['basedir'] . $relative;
        }

        $parsedHost = parse_url($previewUrl, PHP_URL_HOST);
        $homeHost   = parse_url(home_url(), PHP_URL_HOST);
        if ($parsedHost && $homeHost && $parsedHost === $homeHost) {
            $relative = parse_url($previewUrl, PHP_URL_PATH);
            $uploadsRel = parse_url($baseUrl, PHP_URL_PATH);
            if ($relative && $uploadsRel && strpos($relative, $uploadsRel) === 0) {
                return $uploadDir['basedir'] . substr($relative, strlen($uploadsRel) - 1);
            }
        }

        return null;
    }

    /**
     * Remove old preview files inside the dedicated upload subfolder.
     *
     * Bound to the same allowed_base via wp_normalize_path to avoid traversal.
     */
    private function cleanupExistingPreviews(): void
    {
        $uploadDir = wp_upload_dir();
        $previewDir = trailingslashit($uploadDir['basedir']) . 'ultimate-watermark';

        if (!is_dir($previewDir)) {
            return;
        }

        $patterns = [
            $previewDir . '/preview-*.jpg',
            $previewDir . '/preview-*.png',
            $previewDir . '/watermark_preview_*.jpg',
            $previewDir . '/watermark_preview_*.png',
            $previewDir . '/preview-source-*.jpg',
        ];

        $allowedBase = wp_normalize_path($previewDir);

        foreach ($patterns as $pattern) {
            $files = glob($pattern);
            if (!is_array($files)) {
                continue;
            }
            foreach ($files as $file) {
                $normalized = wp_normalize_path($file);
                if (strpos($normalized, $allowedBase) === 0 && is_file($normalized)) {
                    @unlink($normalized);
                }
            }
        }
    }
}
