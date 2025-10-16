<?php

namespace MantraBrain\UltimateWatermark\Ajax;

use MantraBrain\UltimateWatermark\Watermark\WatermarkManager;
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
        add_action('wp_ajax_ultimate_watermark_generate_preview', [$this, 'handleGeneratePreview']);
        add_action('wp_ajax_nopriv_ultimate_watermark_generate_preview', [$this, 'handleGeneratePreview']);
        add_action('wp_ajax_ultimate_watermark_get_library_status', [$this, 'handleGetLibraryStatus']);
        add_action('wp_ajax_nopriv_ultimate_watermark_get_library_status', [$this, 'handleGetLibraryStatus']);
    }

    /**
     * Handle preview generation AJAX request
     */
    public function handleGeneratePreview(): void
    {
        // Check if nonce exists
        if (empty($_POST['nonce'])) {
            wp_send_json_error('No nonce provided');
            return;
        }
        
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'ultimate_watermark_ajax')) {
            wp_send_json_error('Invalid nonce');
            return;
        }

        // Check capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
            return;
        }

        // Check if watermarking is available
        if (!WatermarkManager::isAvailable()) {
            wp_send_json_error('No image processing library available. Please install GD or Imagick extension.');
            return;
        }

        // Get watermark data from POST (WatermarkService will handle sanitization)
        $watermarkData = $_POST;
        error_log("Ultimate Watermark: AJAX raw data: " . print_r($watermarkData, true));
        
        // Get source image path
        $sourceImagePath = $this->getSourceImagePath();
        if (!$sourceImagePath) {
            wp_send_json_error('Source image not found');
            return;
        }
        
        error_log("Ultimate Watermark: Source image path: " . $sourceImagePath);

        try {
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
                        'message' => 'Preview generated successfully'
                    ]);
                } else {
                    wp_send_json_error('Preview file not found');
                }
            } else {
                wp_send_json_error('Failed to generate preview');
            }
        } catch (\Exception $e) {
            wp_send_json_error('Preview generation failed: ' . $e->getMessage());
        }
    }


    /**
     * Handle library status AJAX request
     */
    public function handleGetLibraryStatus(): void
    {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'ultimate_watermark_ajax')) {
            wp_send_json_error('Invalid nonce');
            return;
        }

        // Check capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
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
        // Use the preview image from assets
        $pluginDir = plugin_dir_path(dirname(__DIR__));
        $originalPreviewPath = $pluginDir . 'assets/images/preview-image.jpg';
        
        if (!file_exists($originalPreviewPath)) {
            // Create default preview image if it doesn't exist
            $originalPreviewPath = $this->createDefaultPreviewImage();
        }
        
        // Create a copy of the original preview image for watermarking
        $uploadDir = wp_upload_dir();
        $previewDir = $uploadDir['basedir'] . '/ultimate-watermark';
        
        if (!file_exists($previewDir)) {
            wp_mkdir_p($previewDir);
        }
        
        $copyPath = $previewDir . '/preview-source-' . time() . '.jpg';
        
        // Copy the original preview image
        if (copy($originalPreviewPath, $copyPath)) {
            // Clean up old preview source images (keep only last 10)
            $this->cleanupOldPreviewSources($previewDir);
            return $copyPath;
        }
        
        // Fallback to original if copy fails
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
     * Clean up old preview source images
     */
    private function cleanupOldPreviewSources(string $previewDir): void
    {
        $files = glob($previewDir . '/preview-source-*.jpg');
        
        if (count($files) > 10) {
            // Sort by modification time (oldest first)
            usort($files, function($a, $b) {
                return filemtime($a) - filemtime($b);
            });
            
            // Remove oldest files, keeping only the last 10
            $filesToRemove = array_slice($files, 0, count($files) - 10);
            foreach ($filesToRemove as $file) {
                if (file_exists($file)) {
                    unlink($file);
                }
            }
        }
    }
}
