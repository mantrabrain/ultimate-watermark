<?php

namespace MantraBrain\UltimateWatermark\Watermark\Processors;

use MantraBrain\UltimateWatermark\Watermark\WatermarkProcessorInterface;

/**
 * GD Watermark Processor Class
 * 
 * Handles watermark application using PHP GD library with comprehensive error handling,
 * memory management, and performance optimizations for enterprise-level applications.
 *
 * @package UltimateWatermark
 * @since 2.0.0
 */
class GDWatermarkProcessor implements WatermarkProcessorInterface
{
    /**
     * Maximum memory limit for image processing (256MB)
     */
    private const MAX_MEMORY_LIMIT = '256M';

    /**
     * Maximum image dimensions allowed
     */
    private const MAX_IMAGE_WIDTH = 15000;
    private const MAX_IMAGE_HEIGHT = 15000;
    
    /**
     * Maximum file size for image processing (50MB)
     */
    private const MAX_FILE_SIZE = 52428800; // 50 * 1024 * 1024
    
    /**
     * Default quality settings
     */
    private const DEFAULT_QUALITY = 90;
    private const MIN_QUALITY = 1;
    private const MAX_QUALITY = 100;
    
    /**
     * Default font size limits
     */
    private const MIN_FONT_SIZE = 8;

    /**
     * Supported image formats
     */
    private const SUPPORTED_FORMATS = [
        IMAGETYPE_JPEG => 'jpeg',
        IMAGETYPE_PNG => 'png',
        IMAGETYPE_GIF => 'gif',
        IMAGETYPE_WEBP => 'webp'
    ];

    /**
     * Apply watermark to image with comprehensive error handling
     */
    public function applyWatermark(string $sourceImagePath, string $outputImagePath, array $watermarkData): bool
    {
        try {
            // Validate inputs
            $this->validateInputs($sourceImagePath, $outputImagePath, $watermarkData);

            // Set memory limit and check requirements
            $this->prepareMemoryLimits();

            // Load source image with error handling
            $image = $this->loadImage($sourceImagePath);
            if (!$image) {
                throw new \RuntimeException('Failed to load source image: ' . basename($sourceImagePath));
            }

            try {
                // Scale watermark data proportionally
                $scaledWatermarkData = $this->scaleWatermarkDataForImage($image, $watermarkData);

                // Apply watermark based on type
                $watermarkType = $scaledWatermarkData['watermark_type'] ?? 'text';
                $this->applyWatermarkByType($image, $scaledWatermarkData, $watermarkType);

                // Save watermarked image
                $result = $this->saveImage($image, $outputImagePath, $watermarkData);

                return $result;

            } finally {
                // Always clean up memory
                if (is_resource($image) || $image instanceof \GdImage) {
                    imagedestroy($image);
                }
            }

        } catch (\Exception $e) {
            error_log('Ultimate Watermark GD Processor Error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Generate preview image
     */
    public function generatePreview(string $sourceImagePath, array $watermarkData): string|false
    {
        try {
            $this->validateInputs($sourceImagePath, '', $watermarkData);
            $this->prepareMemoryLimits();

            $image = $this->loadImage($sourceImagePath);
            if (!$image) {
                throw new \RuntimeException('Failed to load source image for preview');
            }

            try {
                $scaledWatermarkData = $this->scaleWatermarkDataForImage($image, $watermarkData);
                $watermarkType = $scaledWatermarkData['watermark_type'] ?? 'text';
                $this->applyWatermarkByType($image, $scaledWatermarkData, $watermarkType);

                // Generate preview URL
                $previewUrl = $this->generatePreviewUrl($image, $watermarkData);
                return $previewUrl;

            } finally {
                if (is_resource($image) || $image instanceof \GdImage) {
                    imagedestroy($image);
                }
            }

        } catch (\Exception $e) {
            error_log('Ultimate Watermark GD Preview Error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Validate input parameters
     */
    private function validateInputs(string $sourcePath, string $outputPath, array $watermarkData): void
    {
        if (!file_exists($sourcePath) || !is_readable($sourcePath)) {
            throw new \InvalidArgumentException('Source image file not found or not readable: ' . basename($sourcePath));
        }

        if (!empty($outputPath)) {
            $outputDir = dirname($outputPath);
            if (!is_dir($outputDir) || !is_writable($outputDir)) {
                throw new \InvalidArgumentException('Output directory is not writable: ' . $outputDir);
            }
        }

        if (empty($watermarkData)) {
            throw new \InvalidArgumentException('Watermark data cannot be empty');
        }

        // Check image format support
        $imageType = exif_imagetype($sourcePath);
        if (!isset(self::SUPPORTED_FORMATS[$imageType])) {
            throw new \InvalidArgumentException('Unsupported image format: ' . $imageType);
        }
    }

    /**
     * Prepare memory limits for image processing
     */
    private function prepareMemoryLimits(): void
    {
        $currentLimit = ini_get('memory_limit');
        
        // Convert current limit to bytes
        $currentBytes = $this->parseMemoryLimit($currentLimit);
        $requiredBytes = $this->parseMemoryLimit(self::MAX_MEMORY_LIMIT);

        if ($currentBytes < $requiredBytes) {
            ini_set('memory_limit', self::MAX_MEMORY_LIMIT);
        }

        // Check if GD is available
        if (!extension_loaded('gd') || !function_exists('gd_info')) {
            throw new \RuntimeException('GD extension is not available');
        }

        $gdInfo = gd_info();
        if (empty($gdInfo['PNG Support']) && empty($gdInfo['JPEG Support'])) {
            throw new \RuntimeException('GD library lacks required image format support');
        }
    }

    /**
     * Parse memory limit string to bytes
     */
    private function parseMemoryLimit(string $limit): int
    {
        $unit = strtolower(substr($limit, -1));
        $value = (int) substr($limit, 0, -1);

        switch ($unit) {
            case 'g':
                return $value * 1024 * 1024 * 1024;
            case 'm':
                return $value * 1024 * 1024;
            case 'k':
                return $value * 1024;
            default:
                return (int) $limit;
        }
    }

    /**
     * Load image with comprehensive error handling
     */
    private function loadImage(string $imagePath): \GdImage|false
    {
        if (!file_exists($imagePath)) {
            return false;
        }

        // Check file size (prevent memory exhaustion)
        if (filesize($imagePath) > self::MAX_FILE_SIZE) {
            throw new \RuntimeException('Image file too large: ' . basename($imagePath));
        }

        $imageInfo = getimagesize($imagePath);
        if (!$imageInfo) {
            throw new \RuntimeException('Unable to get image information: ' . basename($imagePath));
        }

        list($width, $height, $type) = $imageInfo;

        // Validate image dimensions
        if ($width > self::MAX_IMAGE_WIDTH || $height > self::MAX_IMAGE_HEIGHT) {
            throw new \RuntimeException('Image dimensions too large: ' . $width . 'x' . $height);
        }

        // Load image based on type
        switch ($type) {
            case IMAGETYPE_JPEG:
                $image = imagecreatefromjpeg($imagePath);
                break;
            case IMAGETYPE_PNG:
                $image = imagecreatefrompng($imagePath);
                break;
            case IMAGETYPE_GIF:
                $image = imagecreatefromgif($imagePath);
                break;
            case IMAGETYPE_WEBP:
                if (function_exists('imagecreatefromwebp')) {
                    $image = imagecreatefromwebp($imagePath);
                } else {
                    throw new \RuntimeException('WebP format not supported by this GD version');
                }
                break;
            default:
                throw new \RuntimeException('Unsupported image type: ' . $type);
        }

        if (!$image) {
            throw new \RuntimeException('Failed to create image resource from: ' . basename($imagePath));
        }

        return $image;
    }

    /**
     * Apply watermark based on type
     */
    private function applyWatermarkByType(\GdImage $image, array $watermarkData, string $type): void
    {
        // Allow Pro plugin to handle custom watermark types
        $handled = apply_filters('ultimate_watermark_handle_custom_type', false, $image, $watermarkData, $type);
        
        if ($handled) {
            return;
        }
        
        switch ($type) {
            case 'text':
                $this->applyTextWatermark($image, $watermarkData);
                break;
            case 'image':
                $this->applyImageWatermark($image, $watermarkData);
                break;
            default:
                // Allow Pro plugin to register custom types
                do_action('ultimate_watermark_apply_custom_type', $image, $watermarkData, $type);
                
                // If no handler processed it, throw exception
                if (!did_action('ultimate_watermark_custom_type_applied')) {
                    throw new \InvalidArgumentException('Unsupported watermark type: ' . $type);
                }
        }
    }

    /**
     * Scale watermark data proportionally based on image size
     */
    private function scaleWatermarkDataForImage(\GdImage $image, array $watermarkData): array
    {
        $currentWidth = imagesx($image);
        $currentHeight = imagesy($image);

        // Get full size dimensions with fallback strategies
        $fullDimensions = $this->getFullSizeDimensions($watermarkData, $currentWidth, $currentHeight);
        
        // Calculate scaling ratio
        $ratioX = $currentWidth / $fullDimensions['width'];
        $ratioY = $currentHeight / $fullDimensions['height'];
        $scaleRatio = min($ratioX, $ratioY);

        // Create scaled copy
        $scaled = $watermarkData;

        // Scale various watermark properties
        $scaled = $this->scaleTextProperties($scaled, $scaleRatio);
        $scaled = $this->scaleOffsetProperties($scaled, $scaleRatio);
        $scaled = $this->scaleImageProperties($scaled, $scaleRatio);

        return $scaled;
    }

    /**
     * Get full size dimensions using multiple strategies
     */
    private function getFullSizeDimensions(array $watermarkData, int $currentWidth, int $currentHeight): array
    {
        // Strategy 1: From watermark data
        $width = $watermarkData['_full_size_width'] ?? null;
        $height = $watermarkData['_full_size_height'] ?? null;

        if ($width && $height) {
            return ['width' => $width, 'height' => $height];
        }

        // Strategy 2: From source image path
        $sourcePath = $watermarkData['_source_image_path'] ?? '';
        if ($sourcePath && file_exists($sourcePath)) {
            $attachmentId = $this->getAttachmentIdFromPath($sourcePath);
            if ($attachmentId) {
                $metadata = wp_get_attachment_metadata($attachmentId);
                if ($metadata && isset($metadata['width'], $metadata['height'])) {
                    return ['width' => $metadata['width'], 'height' => $metadata['height']];
                }
            }
        }

        // Strategy 3: Use current image dimensions (fallback)
        // This is safe for preview generation and when full size info is unavailable
        return ['width' => $currentWidth, 'height' => $currentHeight];
    }

    /**
     * Scale text-related properties
     */
    private function scaleTextProperties(array $data, float $ratio): array
    {
        if (isset($data['watermark_font_size'])) {
            $data['watermark_font_size'] = max(self::MIN_FONT_SIZE, (int) round($data['watermark_font_size'] * $ratio));
        }

        return $data;
    }

    /**
     * Scale offset properties
     */
    private function scaleOffsetProperties(array $data, float $ratio): array
    {
        if (isset($data['watermark_offset_x'])) {
            $data['watermark_offset_x'] = max(0, (int) round($data['watermark_offset_x'] * $ratio));
        }
        if (isset($data['watermark_offset_y'])) {
            $data['watermark_offset_y'] = max(0, (int) round($data['watermark_offset_y'] * $ratio));
        }

        return $data;
    }

    /**
     * Scale image-related properties
     */
    private function scaleImageProperties(array $data, float $ratio): array
    {
        if (isset($data['watermark_custom_width'])) {
            $data['watermark_custom_width'] = max(1, (int) round($data['watermark_custom_width'] * $ratio));
        }
        if (isset($data['watermark_custom_height'])) {
            $data['watermark_custom_height'] = max(1, (int) round($data['watermark_custom_height'] * $ratio));
        }

        return $data;
    }

    /**
     * Apply text watermark
     */
    private function applyTextWatermark(\GdImage $image, array $watermarkData): void
    {
        $text = $watermarkData['watermark_text'] ?? '';
        $fontSize = $watermarkData['watermark_font_size'] ?? 20;
        $color = $watermarkData['watermark_color'] ?? '#000000';
        $opacity = $watermarkData['watermark_opacity'] ?? 50;

        if (empty($text)) {
            throw new \InvalidArgumentException('Watermark text cannot be empty');
        }

        // Convert hex color to RGB
        $rgb = $this->hexToRgb($color);
        $alpha = $this->calculateAlpha($opacity);

        // Allocate color
        $textColor = imagecolorallocatealpha($image, $rgb['red'], $rgb['green'], $rgb['blue'], $alpha);

        // Calculate text position
        $position = $this->calculateTextPosition($image, $text, $fontSize, $watermarkData);

        // Add text with error handling
        $result = imagettftext($image, $fontSize, 0, $position['x'], $position['y'], $textColor, $this->getFontPath(), $text);
        
        if ($result === false) {
            throw new \RuntimeException('Failed to apply text watermark');
        }
    }

    /**
     * Apply image watermark
     */
    private function applyImageWatermark(\GdImage $image, array $watermarkData): void
    {
        $imageId = $watermarkData['watermark_image_id'] ?? 0;
        if (!$imageId) {
            throw new \InvalidArgumentException('Watermark image ID is required');
        }

        // Get watermark image path
        $watermarkPath = get_attached_file($imageId);
        if (!file_exists($watermarkPath)) {
            throw new \RuntimeException('Watermark image file not found');
        }

        // Load watermark image
        $watermarkImg = $this->loadImage($watermarkPath);
        if (!$watermarkImg) {
            throw new \RuntimeException('Failed to load watermark image');
        }

        try {
            // Calculate watermark dimensions and position
            $watermarkDimensions = $this->calculateWatermarkDimensions($watermarkImg, $watermarkData);
            $position = $this->calculateImagePosition($image, $watermarkDimensions, $watermarkData);

            // Apply transparency
            $opacity = $watermarkData['watermark_opacity'] ?? 50;
            $this->applyImageTransparency($watermarkImg, $opacity);

            // Copy watermark to main image
            $result = imagecopyresampled(
                $image,
                $watermarkImg,
                $position['x'],
                $position['y'],
                0,
                0,
                $watermarkDimensions['width'],
                $watermarkDimensions['height'],
                imagesx($watermarkImg),
                imagesy($watermarkImg)
            );

            if (!$result) {
                throw new \RuntimeException('Failed to apply image watermark');
            }

        } finally {
            if (is_resource($watermarkImg) || $watermarkImg instanceof \GdImage) {
                imagedestroy($watermarkImg);
            }
        }
    }

    /**
     * Save image with quality and format settings
     */
    private function saveImage(\GdImage $image, string $outputPath, array $watermarkData): bool
    {
        $quality = $watermarkData['watermark_quality'] ?? self::DEFAULT_QUALITY;
        $imageFormat = $watermarkData['image_format'] ?? 'baseline';

        // Validate quality
        $quality = max(self::MIN_QUALITY, min(self::MAX_QUALITY, (int) $quality));

        // Apply interlace mode (progressive vs baseline)
        if ($imageFormat === 'progressive') {
            imageinterlace($image, true);
        }

        // Ensure output directory exists
        $outputDir = dirname($outputPath);
        if (!is_dir($outputDir)) {
            wp_mkdir_p($outputDir);
        }

        // Detect format from file extension
        $extension = strtolower(pathinfo($outputPath, PATHINFO_EXTENSION));

        // Save based on file extension
        switch ($extension) {
            case 'jpeg':
            case 'jpg':
                return imagejpeg($image, $outputPath, $quality);
            case 'png':
                // Convert quality to PNG compression level (0-9)
                $pngQuality = 9 - round(($quality / 100) * 9);
                return imagepng($image, $outputPath, (int) $pngQuality);
            case 'gif':
                return imagegif($image, $outputPath);
            case 'webp':
                if (function_exists('imagewebp')) {
                    return imagewebp($image, $outputPath, $quality);
                } else {
                    throw new \RuntimeException('WebP output not supported');
                }
            default:
                // Fallback: save as JPEG
                return imagejpeg($image, $outputPath, $quality);
        }
    }

    /**
     * Generate preview URL
     */
    private function generatePreviewUrl(\GdImage $image, array $watermarkData): string
    {
        // Create temporary file for preview
        $previewDir = wp_upload_dir()['basedir'] . '/ultimate-watermark-previews/';
        if (!is_dir($previewDir)) {
            wp_mkdir_p($previewDir);
        }

        $previewPath = $previewDir . 'preview_' . uniqid() . '.jpg';
        
        // Save preview as JPEG with decent quality
        $this->saveImage($image, $previewPath, array_merge($watermarkData, [
            'watermark_quality' => 85
        ]));

        // Convert to URL
        $previewUrl = str_replace(wp_upload_dir()['basedir'], wp_upload_dir()['baseurl'], $previewPath);
        
        return $previewUrl;
    }

    /**
     * Get attachment ID from image file path
     * Handles both full size images and thumbnails/resized versions
     */
    private function getAttachmentIdFromPath(string $imagePath): ?int
    {
        if (empty($imagePath)) {
            return null;
        }
        
        // Normalize path
        $normalizedPath = wp_normalize_path($imagePath);
        $uploadDir = wp_upload_dir();
        $baseDir = wp_normalize_path($uploadDir['basedir']);
        
        // Get relative path from uploads directory
        if (strpos($normalizedPath, $baseDir) !== 0) {
            return null;
        }
        
        $relativePath = str_replace($baseDir . '/', '', $normalizedPath);
        $pathInfo = pathinfo($relativePath);
        $baseFilename = $pathInfo['filename'];
        $directory = $pathInfo['dirname'];
        
        // First, try exact match (for full size images)
        global $wpdb;
        $attachmentId = $wpdb->get_var($wpdb->prepare(
            "SELECT post_id FROM {$wpdb->postmeta} 
            WHERE meta_key = '_wp_attached_file' 
            AND meta_value = %s 
            LIMIT 1",
            $relativePath
        ));
        
        if ($attachmentId) {
            return (int) $attachmentId;
        }
        
        // If exact match fails, try to match by base filename and directory
        // This handles thumbnails and resized images (e.g., image-150x150.jpg -> image.jpg)
        // Remove size suffix (e.g., -150x150) from filename
        $baseFilenameClean = preg_replace('/-\d+x\d+$/', '', $baseFilename);
        $extension = isset($pathInfo['extension']) ? '.' . $pathInfo['extension'] : '';
        
        // Try to find attachment with matching base filename in same directory
        $possiblePath = $directory === '.' ? $baseFilenameClean . $extension : $directory . '/' . $baseFilenameClean . $extension;
        
        $attachmentId = $wpdb->get_var($wpdb->prepare(
            "SELECT post_id FROM {$wpdb->postmeta} 
            WHERE meta_key = '_wp_attached_file' 
            AND meta_value = %s 
            LIMIT 1",
            $possiblePath
        ));
        
        if ($attachmentId) {
            return (int) $attachmentId;
        }
        
        // Last resort: search by base filename pattern in metadata
        $attachmentId = $wpdb->get_var($wpdb->prepare(
            "SELECT post_id FROM {$wpdb->postmeta} pm
            INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
            WHERE pm.meta_key = '_wp_attached_file'
            AND pm.meta_value LIKE %s
            AND p.post_type = 'attachment'
            LIMIT 1",
            '%' . $wpdb->esc_like($baseFilenameClean) . '%'
        ));
        
        return $attachmentId ? (int) $attachmentId : null;
    }

    /**
     * Convert hex color to RGB
     */
    private function hexToRgb(string $hex): array
    {
        $hex = ltrim($hex, '#');
        
        if (strlen($hex) === 3) {
            $r = hexdec(substr($hex, 0, 1) . substr($hex, 0, 1));
            $g = hexdec(substr($hex, 1, 1) . substr($hex, 1, 1));
            $b = hexdec(substr($hex, 2, 1) . substr($hex, 2, 1));
        } else {
            $r = hexdec(substr($hex, 0, 2));
            $g = hexdec(substr($hex, 2, 2));
            $b = hexdec(substr($hex, 4, 2));
        }

        return ['red' => $r, 'green' => $g, 'blue' => $b];
    }

    /**
     * Calculate alpha value from transparency percentage
     */
    private function calculateAlpha(int $transparency): int
    {
        // Convert transparency (0-100) to alpha (0-127)
        // 0% transparency = fully opaque = alpha 0
        // 100% transparency = fully transparent = alpha 127
        return (int) round((100 - $transparency) * 1.27);
    }

    /**
     * Get font path for text watermarks
     */
    private function getFontPath(): string
    {
        // Try to use a system font, fallback to default
        $fontPaths = [
            '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
            '/Windows/Fonts/arial.ttf',
            '/System/Library/Fonts/Arial.ttf',
            plugin_dir_path(ULTIMATE_WATERMARK_FILE) . 'assets/fonts/default.ttf'
        ];

        foreach ($fontPaths as $fontPath) {
            if (file_exists($fontPath)) {
                return $fontPath;
            }
        }

        // Fallback to built-in font (may not work with imagettftext)
        return '';
    }

    /**
     * Calculate text position
     */
    private function calculateTextPosition(\GdImage $image, string $text, int $fontSize, array $watermarkData): array
    {
        $imageWidth = imagesx($image);
        $imageHeight = imagesy($image);

        // Get text bounding box
        $bbox = imagettfbbox($fontSize, 0, $this->getFontPath(), $text);
        $textWidth = $bbox[2] - $bbox[0];
        $textHeight = $bbox[1] - $bbox[7];

        // Calculate position based on alignment
        $position = $this->calculateAlignmentPosition(
            $imageWidth,
            $imageHeight,
            $textWidth,
            $textHeight,
            $watermarkData
        );

        return $position;
    }

    /**
     * Calculate image position
     */
    private function calculateImagePosition(\GdImage $image, array $watermarkDimensions, array $watermarkData): array
    {
        $imageWidth = imagesx($image);
        $imageHeight = imagesy($image);

        return $this->calculateAlignmentPosition(
            $imageWidth,
            $imageHeight,
            $watermarkDimensions['width'],
            $watermarkDimensions['height'],
            $watermarkData
        );
    }

    /**
     * Calculate alignment position
     */
    private function calculateAlignmentPosition(int $imageWidth, int $imageHeight, int $itemWidth, int $itemHeight, array $watermarkData): array
    {
        $position = $watermarkData['watermark_position'] ?? 'bottom-right';
        $offsetX = $watermarkData['watermark_offset_x'] ?? 0;
        $offsetY = $watermarkData['watermark_offset_y'] ?? 0;

        $x = 0;
        $y = 0;

        // Position uses hyphen format: top-left, center-right, bottom-right, etc.
        switch ($position) {
            case 'top-left':
                $x = $offsetX;
                $y = $offsetY + $itemHeight;
                break;
            case 'top-center':
                $x = ($imageWidth - $itemWidth) / 2;
                $y = $offsetY + $itemHeight;
                break;
            case 'top-right':
                $x = $imageWidth - $itemWidth - $offsetX;
                $y = $offsetY + $itemHeight;
                break;
            case 'center-left':
                $x = $offsetX;
                $y = ($imageHeight + $itemHeight) / 2;
                break;
            case 'center':
                $x = ($imageWidth - $itemWidth) / 2;
                $y = ($imageHeight + $itemHeight) / 2;
                break;
            case 'center-right':
                $x = $imageWidth - $itemWidth - $offsetX;
                $y = ($imageHeight + $itemHeight) / 2;
                break;
            case 'bottom-left':
                $x = $offsetX;
                $y = $imageHeight - $offsetY;
                break;
            case 'bottom-center':
                $x = ($imageWidth - $itemWidth) / 2;
                $y = $imageHeight - $offsetY;
                break;
            case 'bottom-right':
            default:
                $x = $imageWidth - $itemWidth - $offsetX;
                $y = $imageHeight - $offsetY;
                break;
        }

        return ['x' => (int) $x, 'y' => (int) $y];
    }

    /**
     * Calculate watermark dimensions
     */
    private function calculateWatermarkDimensions(\GdImage $watermarkImage, array $watermarkData): array
    {
        $originalWidth = imagesx($watermarkImage);
        $originalHeight = imagesy($watermarkImage);

        $sizeType = $watermarkData['watermark_size_type'] ?? 'original';

        switch ($sizeType) {
            case 'original':
                return ['width' => $originalWidth, 'height' => $originalHeight];
            
            case 'custom':
                return [
                    'width' => $watermarkData['watermark_custom_width'] ?? $originalWidth,
                    'height' => $watermarkData['watermark_custom_height'] ?? $originalHeight
                ];
            
            case 'scaled':
                $scale = ($watermarkData['watermark_scale_percentage'] ?? 50) / 100;
                return [
                    'width' => (int) round($originalWidth * $scale),
                    'height' => (int) round($originalHeight * $scale)
                ];
            
            default:
                return ['width' => $originalWidth, 'height' => $originalHeight];
        }
    }

    /**
     * Apply transparency to image
     */
    private function applyImageTransparency(\GdImage $image, int $transparency): void
    {
        if ($transparency >= 100) {
            return; // Fully opaque, no changes needed
        }

        $alpha = $this->calculateAlpha($transparency);
        
        // Create transparent overlay
        imagealphablending($image, false);
        imagesavealpha($image, true);
        
        // Apply alpha blending
        for ($x = 0; $x < imagesx($image); $x++) {
            for ($y = 0; $y < imagesy($image); $y++) {
                $color = imagecolorat($image, $x, $y);
                $colors = imagecolorsforindex($image, $color);
                imagesetpixel($image, $x, $y, imagecolorallocatealpha($image, $colors['red'], $colors['green'], $colors['blue'], $alpha));
            }
        }
    }

    /**
     * Get supported image formats
     * 
     * @return array Array of supported formats
     */
    public function getSupportedFormats(): array
    {
        return ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    }

    /**
     * Check if processor is available
     * 
     * @return bool True if GD library is available
     */
    public function isAvailable(): bool
    {
        return extension_loaded('gd') && function_exists('gd_info');
    }
}