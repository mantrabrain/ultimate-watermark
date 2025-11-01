<?php

namespace MantraBrain\UltimateWatermark\Utils;

/**
 * Preview Manager
 * 
 * Manages watermark preview images to ensure only one preview per watermark template
 * 
 * @package UltimateWatermark
 * @since 2.0.0
 */
class PreviewManager
{
    /**
     * Clean up old preview images
     * 
     * @param int $watermark_id Optional watermark ID to clean specific previews
     * @return int Number of files cleaned up
     */
    public static function cleanupOldPreviews(?int $watermark_id = null): int
    {
        $uploadDir = wp_upload_dir();
        $previewDir = $uploadDir['basedir'] . '/ultimate-watermark';
        
        if (!file_exists($previewDir)) {
            return 0;
        }
        
        $cleanedCount = 0;
        
        // Get all preview files
        $previewFiles = glob($previewDir . '/preview-*.jpg');
        $watermarkPreviewFiles = glob($previewDir . '/watermark_preview_*.png');
        
        $allFiles = array_merge($previewFiles, $watermarkPreviewFiles);
        
        // If specific watermark ID provided, clean only that watermark's previews
        if ($watermark_id !== null) {
            $allFiles = array_filter($allFiles, function($file) use ($watermark_id) {
                return strpos(basename($file), 'preview-' . $watermark_id) !== false;
            });
        }
        
        $upload_dir = wp_upload_dir();
        $allowed_base = $upload_dir['basedir'] . '/ultimate-watermark';
        
        foreach ($allFiles as $file) {
            // Security: Validate path to prevent directory traversal
            $normalized_file = wp_normalize_path($file);
            if (strpos($normalized_file, $allowed_base) === 0 && file_exists($normalized_file) && is_file($normalized_file)) {
                // Delete file if it's older than 1 hour or if we're cleaning specific watermark
                $fileAge = time() - filemtime($normalized_file);
                if ($fileAge > 3600 || $watermark_id !== null) { // 1 hour or specific watermark
                    if (unlink($normalized_file)) {
                        $cleanedCount++;
                    }
                }
            }
        }
        
        return $cleanedCount;
    }
    
    /**
     * Clean up all preview images for a specific watermark
     * 
     * @param int $watermark_id Watermark ID
     * @return int Number of files cleaned up
     */
    public static function cleanupWatermarkPreviews(int $watermark_id): int
    {
        return self::cleanupOldPreviews($watermark_id);
    }
    
    /**
     * Get preview URL for a watermark
     * 
     * @param int $watermark_id Watermark ID
     * @return string|null Preview URL or null if not found
     */
    public static function getWatermarkPreviewUrl(int $watermark_id): ?string
    {
        $uploadDir = wp_upload_dir();
        $previewDir = $uploadDir['basedir'] . '/ultimate-watermark';
        
        // Look for watermark-specific preview
        $previewFile = $previewDir . '/watermark-preview-' . $watermark_id . '.jpg';
        
        if (file_exists($previewFile)) {
            return $uploadDir['baseurl'] . '/ultimate-watermark/watermark-preview-' . $watermark_id . '.jpg';
        }
        
        return null;
    }
    
    /**
     * Clean up all old preview images (maintenance function)
     * 
     * @return int Number of files cleaned up
     */
    public static function cleanupAllOldPreviews(): int
    {
        return self::cleanupOldPreviews();
    }
    
    /**
     * Get preview directory size
     * 
     * @return array Directory size information
     */
    public static function getPreviewDirectoryInfo(): array
    {
        $uploadDir = wp_upload_dir();
        $previewDir = $uploadDir['basedir'] . '/ultimate-watermark';
        
        if (!file_exists($previewDir)) {
            return [
                'exists' => false,
                'file_count' => 0,
                'total_size' => 0,
                'files' => []
            ];
        }
        
        $files = glob($previewDir . '/*');
        $fileCount = count($files);
        $totalSize = 0;
        $fileList = [];
        
        foreach ($files as $file) {
            if (is_file($file)) {
                $size = filesize($file);
                $totalSize += $size;
                $fileList[] = [
                    'name' => basename($file),
                    'size' => $size,
                    'modified' => filemtime($file)
                ];
            }
        }
        
        return [
            'exists' => true,
            'file_count' => $fileCount,
            'total_size' => $totalSize,
            'files' => $fileList
        ];
    }
}
