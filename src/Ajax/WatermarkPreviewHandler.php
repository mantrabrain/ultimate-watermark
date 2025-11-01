<?php

namespace MantraBrain\UltimateWatermark\Ajax;

use MantraBrain\UltimateWatermark\Watermark\WatermarkService;
use MantraBrain\UltimateWatermark\Watermark\LibraryDetector;

/**
 * Watermark Preview AJAX Handler
 * 
 * Handles AJAX requests for watermark preview generation
 */
class WatermarkPreviewHandler
{
    public function __construct()
    {
        // Admin-only endpoints - removed wp_ajax_nopriv for security
        // These endpoints require manage_options capability, so they should only be accessible to logged-in admins
        add_action('wp_ajax_ultimate_watermark_generate_preview', [$this, 'handleGeneratePreview']);
        add_action('wp_ajax_ultimate_watermark_get_library_status', [$this, 'handleGetLibraryStatus']);
    }

    /**
     * Handle preview generation AJAX request
     */
    public function handleGeneratePreview(): void
    {
        // Check if nonce exists
        if (empty($_POST['nonce'])) {
            wp_send_json_error(['message' => __('No nonce provided.', 'ultimate-watermark')]);
            return;
        }
        
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'ultimate_watermark_ajax')) {
            wp_send_json_error(['message' => __('Security check failed.', 'ultimate-watermark')]);
            return;
        }

        // Check capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions.', 'ultimate-watermark')]);
            return;
        }

        // Check if watermarking is available
        if (!WatermarkService::isAvailable()) {
            wp_send_json_error(['message' => __('No image processing library available. Please install GD or Imagick extension.', 'ultimate-watermark')]);
            return;
        }

        // Get and sanitize watermark data from POST
        $watermarkData = [];
        foreach ($_POST as $key => $value) {
            if ($key !== 'nonce' && $key !== 'action') {
                if (is_array($value)) {
                    $watermarkData[$key] = array_map('sanitize_text_field', $value);
                } else {
                    $watermarkData[$key] = sanitize_text_field($value);
                }
            }
        }
        
        // Get source image path
        $sourceImagePath = $this->getSourceImagePath();
        if (!$sourceImagePath) {
            wp_send_json_error(['message' => __('Source image not found.', 'ultimate-watermark')]);
            return;
        }
        

        try {
            // Clean up any existing preview images before generating new one
            $this->cleanupExistingPreviews();
            
            // Generate preview using WatermarkService
            $previewResult = \MantraBrain\UltimateWatermark\Watermark\WatermarkService::generatePreview($sourceImagePath, $watermarkData);
            
            if ($previewResult) {
                // Check if result is a URL (new format) or file path (old format)
                if (filter_var($previewResult, FILTER_VALIDATE_URL)) {
                    // New format: result is already a URL
                    $previewUrl = $previewResult;
                    $previewPath = $this->getPreviewPathFromUrl($previewUrl);
                } else {
                    // Old format: result is a file path
                    $previewPath = $previewResult;
                    $previewUrl = $this->getPreviewUrl($previewPath);
                }
                
                // Verify the file exists
                if ($previewPath && file_exists($previewPath)) {
                    wp_send_json_success([
                        'preview_url' => $previewUrl,
                        'preview_path' => $previewPath,
                        'library_used' => \MantraBrain\UltimateWatermark\Watermark\WatermarkService::getCurrentLibrary(),
                        'message' => __('Preview generated successfully.', 'ultimate-watermark')
                    ]);
                } else {
                    wp_send_json_error(['message' => __('Preview file not found.', 'ultimate-watermark')]);
                }
            } else {
                wp_send_json_error(['message' => __('Failed to generate preview.', 'ultimate-watermark')]);
            }
        } catch (\Exception $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Ultimate Watermark Preview Error: ' . $e->getMessage());
            }
            wp_send_json_error(['message' => __('Preview generation failed.', 'ultimate-watermark')]);
        }
    }


    /**
     * Handle library status AJAX request
     */
    public function handleGetLibraryStatus(): void
    {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'ultimate_watermark_ajax')) {
            wp_send_json_error(['message' => __('Security check failed.', 'ultimate-watermark')]);
            return;
        }

        // Check capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions.', 'ultimate-watermark')]);
            return;
        }

        $status = \MantraBrain\UltimateWatermark\Watermark\WatermarkService::getLibraryStatus();
        $isAvailable = \MantraBrain\UltimateWatermark\Watermark\WatermarkService::isAvailable();
        $currentLibrary = \MantraBrain\UltimateWatermark\Watermark\WatermarkService::getCurrentLibrary();
        $supportedFormats = \MantraBrain\UltimateWatermark\Watermark\WatermarkProcessorFactory::getSupportedFormats();

        wp_send_json_success([
            'libraries' => $status,
            'is_available' => $isAvailable,
            'current_library' => $currentLibrary,
            'supported_formats' => $supportedFormats
        ]);
    }


    /**
     * Get source image path for preview
     */
    private function getSourceImagePath(): string
    {
        // Always use the original preview image from assets (never modify it)
        $pluginDir = plugin_dir_path(dirname(__DIR__));
        $originalPreviewPath = $pluginDir . 'assets/images/preview-image.jpg';
        
        if (!file_exists($originalPreviewPath)) {
            // Create default preview image if it doesn't exist
            $originalPreviewPath = $this->createDefaultPreviewImage();
        }
        
        // Always return the original image path (never create copies)
        return $originalPreviewPath;
    }

    /**
     * Create default preview image if none exists
     */
    private function createDefaultPreviewImage(): string
    {
        $pluginDir = plugin_dir_path(dirname(__DIR__));
        $previewImagePath = $pluginDir . 'assets/images/preview-image.jpg';
        
        // Create a simple preview image using GD
        if (extension_loaded('gd')) {
            $image = imagecreate(400, 300);
            $bgColor = imagecolorallocate($image, 240, 240, 240);
            $textColor = imagecolorallocate($image, 100, 100, 100);
            
            imagefill($image, 0, 0, $bgColor);
            imagestring($image, 5, 150, 140, 'Preview Image', $textColor);
            
            imagejpeg($image, $previewImagePath, 90);
            imagedestroy($image);
        }
        
        return $previewImagePath;
    }

    /**
     * Get preview URL from path
     */
    private function getPreviewUrl(string $previewPath): string
    {
        $uploadDir = wp_upload_dir();
        $previewDir = $uploadDir['baseurl'] . '/ultimate-watermark';
        
        $filename = basename($previewPath);
        return $previewDir . '/' . $filename;
    }
    
    private function getPreviewPathFromUrl(string $previewUrl): string
    {
        $uploadDir = wp_upload_dir();
        $relativePath = str_replace($uploadDir['baseurl'], '', $previewUrl);
        return $uploadDir['basedir'] . $relativePath;
    }

    /**
     * Clean up existing preview images before generating new one
     */
    private function cleanupExistingPreviews(): void
    {
        $uploadDir = wp_upload_dir();
        $previewDir = $uploadDir['basedir'] . '/ultimate-watermark';
        
        if (!file_exists($previewDir)) {
            return;
        }
        
        // Remove all existing preview images
        $previewFiles = glob($previewDir . '/preview-*.jpg');
        $watermarkPreviewFiles = glob($previewDir . '/watermark_preview_*.png');
        $previewSourceFiles = glob($previewDir . '/preview-source-*.jpg');
        
        $allFiles = array_merge($previewFiles, $watermarkPreviewFiles, $previewSourceFiles);
        
        $upload_dir = wp_upload_dir();
        $allowed_base = $upload_dir['basedir'] . '/ultimate-watermark';
        
        foreach ($allFiles as $file) {
            // Security: Validate path to prevent directory traversal
            $normalized_file = wp_normalize_path($file);
            if (strpos($normalized_file, $allowed_base) === 0 && file_exists($normalized_file) && is_file($normalized_file)) {
                unlink($normalized_file);
            }
        }
    }
}
