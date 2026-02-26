<?php

namespace MantraBrain\UltimateWatermark\Watermark\Processors;

use MantraBrain\UltimateWatermark\Watermark\WatermarkProcessorInterface;

class ImagickWatermarkProcessor implements WatermarkProcessorInterface
{
    /**
     * Apply watermark to image
     */
    public function applyWatermark(string $sourceImagePath, string $outputImagePath, array $watermarkData): bool
    {
        
        // Load source image
        $image = new \Imagick($sourceImagePath);
        if (!$image) {
            return false;
        }
        
        // Scale watermark data proportionally based on image size
        $scaledWatermarkData = $this->scaleWatermarkDataForImage($image, $watermarkData);
        
        $watermarkType = $scaledWatermarkData['watermark_type'] ?? 'text';
        
        if ($watermarkType === 'text') {
            $this->applyTextWatermark($image, $scaledWatermarkData);
        } elseif ($watermarkType === 'image') {
            $this->applyImageWatermark($image, $scaledWatermarkData);
        } else {
        }
        
        // Save watermarked image with quality and format settings
        $result = $this->saveImage($image, $outputImagePath, $watermarkData);
        $image->destroy();
        
        return $result;
    }
    
    /**
     * Scale watermark data proportionally based on current image size vs full size (original)
     * This ensures watermarks look correct on different image sizes (thumbnails, medium, full, etc.)
     * The watermark is configured for the FULL SIZE image, and we scale DOWN for smaller sizes
     */
    private function scaleWatermarkDataForImage(\Imagick $image, array $watermarkData): array
    {
        // Get current image dimensions
        $currentWidth = $image->getImageWidth();
        $currentHeight = $image->getImageHeight();
        
        // Get full size (original) image dimensions
        // First, try to get from watermark data if stored
        $fullWidth = $watermarkData['_full_size_width'] ?? null;
        $fullHeight = $watermarkData['_full_size_height'] ?? null;
        
        // If not in watermark data, try to get from source image path context
        if ($fullWidth === null || $fullHeight === null) {
            // Try to get attachment ID from source image path
            $attachmentId = $this->getAttachmentIdFromPath($watermarkData['_source_image_path'] ?? '');
            
            if ($attachmentId) {
                // Get full size image metadata
                $metadata = wp_get_attachment_metadata($attachmentId);
                if ($metadata && isset($metadata['width']) && isset($metadata['height'])) {
                    $fullWidth = $metadata['width'];
                    $fullHeight = $metadata['height'];
                }
            }
        }
        
        // If still don't have full size dimensions, assume current image IS full size
        if ($fullWidth === null || $fullHeight === null) {
            $fullWidth = $currentWidth;
            $fullHeight = $currentHeight;
        }
        
        // Calculate scaling ratio: current size / full size
        // This will be < 1 for smaller sizes (thumbnail, medium, etc.) and = 1 for full size
        $ratioX = $currentWidth / $fullWidth;
        $ratioY = $currentHeight / $fullHeight;
        $scaleRatio = min($ratioX, $ratioY); // Use minimum to ensure watermark fits
        
        // Create scaled copy of watermark data
        $scaled = $watermarkData;
        
        // Scale font size for text watermarks
        if (isset($scaled['watermark_font_size'])) {
            $scaled['watermark_font_size'] = max(8, (int) round($scaled['watermark_font_size'] * $scaleRatio)); // Minimum 8px
        }
        
        // Scale offset values
        if (isset($scaled['watermark_offset_x'])) {
            $scaled['watermark_offset_x'] = max(0, (int) round($scaled['watermark_offset_x'] * $scaleRatio));
        }
        if (isset($scaled['watermark_offset_y'])) {
            $scaled['watermark_offset_y'] = max(0, (int) round($scaled['watermark_offset_y'] * $scaleRatio));
        }
        
        // Scale custom dimensions for image watermarks
        if (isset($scaled['watermark_custom_width'])) {
            $scaled['watermark_custom_width'] = max(10, (int) round($scaled['watermark_custom_width'] * $scaleRatio)); // Minimum 10px
        }
        if (isset($scaled['watermark_custom_height'])) {
            $scaled['watermark_custom_height'] = max(10, (int) round($scaled['watermark_custom_height'] * $scaleRatio)); // Minimum 10px
        }
        
        // Store current image dimensions for reference
        $scaled['_current_image_width'] = $currentWidth;
        $scaled['_current_image_height'] = $currentHeight;
        $scaled['_scale_ratio'] = $scaleRatio;
        
        return $scaled;
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
     * Generate preview image
     */
    public function generatePreview(string $sourceImagePath, array $watermarkData)
    {
        
        // Load source image
        $image = new \Imagick($sourceImagePath);
        if (!$image) {
            return false;
        }
        
        $watermarkType = $watermarkData['watermark_type'] ?? 'text';
        
        if ($watermarkType === 'text') {
            $this->applyTextWatermark($image, $watermarkData);
        } elseif ($watermarkType === 'image') {
            $this->applyImageWatermark($image, $watermarkData);
        } else {
        }
        
        // Create preview path in WordPress uploads directory
        $uploadDir = wp_upload_dir();
        
        // Clean up any existing preview images first
        $previewDir = $uploadDir['basedir'] . '/ultimate-watermark';
        if (file_exists($previewDir)) {
            $existingFiles = glob($previewDir . '/watermark_preview_*.png');
            foreach ($existingFiles as $file) {
                // Security: Validate path to prevent directory traversal
                $normalized_file = wp_normalize_path($file);
                if (strpos($normalized_file, $previewDir) === 0 && file_exists($normalized_file) && is_file($normalized_file)) {
                    unlink($normalized_file);
                }
            }
        }
        
        // Generate a consistent filename based on watermark data hash
        $watermarkHash = md5(serialize($watermarkData));
        $previewPath = $uploadDir['basedir'] . '/ultimate-watermark/watermark_preview_' . $watermarkHash . '.png';
        
        // Ensure directory exists
        $previewDir = dirname($previewPath);
        if (!file_exists($previewDir)) {
            wp_mkdir_p($previewDir);
        }
        
        // Save preview image with quality and format settings
        $result = $this->saveImage($image, $previewPath, $watermarkData);
        $image->destroy();
        
        if ($result) {
            // Return the web-accessible URL instead of the file path
            $uploadDir = wp_upload_dir();
            $relativePath = str_replace($uploadDir['basedir'], '', $previewPath);
            return $uploadDir['baseurl'] . $relativePath;
        }
        
        return false;
    }
    
    /**
     * Get supported image formats
     */
    public function getSupportedFormats(): array
    {
        return ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    }
    
    /**
     * Apply text watermark - Simple and clean implementation
     */
    private function applyTextWatermark(\Imagick $image, array $watermarkData): void
    {
        
        $text = $watermarkData['watermark_text'] ?? 'Watermark';
        $fontSize = $watermarkData['watermark_font_size'] ?? 24;
        $color = $this->hexToRgb($watermarkData['watermark_color'] ?? '#000000');
        $opacity = $watermarkData['watermark_opacity'] ?? 50;
        $rotation = $watermarkData['watermark_rotation'] ?? 0;
        $fontFamily = $watermarkData['watermark_font_family'] ?? 'Arial';
        
        
        $imageWidth = $image->getImageWidth();
        $imageHeight = $image->getImageHeight();
        
        // Map font family names to system font names with weight and style
        $fontWeight = $watermarkData['watermark_font_weight'] ?? 'normal';
        $fontStyle = $watermarkData['watermark_font_style'] ?? 'normal';
        $systemFontName = $this->getSystemFontName($fontFamily, $fontWeight, $fontStyle);
        
        
        
        // Create a temporary draw object to get accurate text dimensions
        $tempDraw = new \ImagickDraw();
        $tempDraw->setFontSize($fontSize);
        
        // Try to set the font with error handling
        try {
            $tempDraw->setFont($systemFontName);
        } catch (Exception $e) {
            // Fallback to basic Arial
            $tempDraw->setFont('Arial');
            $systemFontName = 'Arial';
        }
        
        // Create text watermark
        $draw = new \ImagickDraw();
        $draw->setFontSize($fontSize);
        $draw->setFillColor($color);
        $draw->setFillOpacity($opacity / 100);
        
        // Try to set the font with error handling
        try {
            $draw->setFont($systemFontName);
        } catch (Exception $e) {
            // Fallback to basic Arial
            $draw->setFont('Arial');
        }
        
        // Apply text with rotation (position will be calculated inside based on rotation)
        $this->drawTextWithRotation($image, $draw, $text, $watermarkData);
    }
    
    /**
     * Draw text with rotation
     */
    private function drawTextWithRotation(\Imagick $image, \ImagickDraw $draw, string $text, array $watermarkData): void
    {
        $rotation = $watermarkData['watermark_rotation'] ?? 0;
        
        // If no rotation, use simple positioning
        if ($rotation == 0) {
            // Calculate position for non-rotated text
            $textMetrics = $image->queryFontMetrics($draw, $text);
            $textWidth = (int) $textMetrics['textWidth'];
            $textHeight = (int) $textMetrics['textHeight'];
            $position = $this->calculatePosition($watermarkData, $image->getImageWidth(), $image->getImageHeight(), $textWidth, $textHeight);
            $this->drawTextAtPosition($image, $draw, $text, $position, $watermarkData);
            return;
        }
        
        // For rotated text, we need to create a temporary image and rotate it
        $this->drawRotatedText($image, $draw, $text, $rotation, $watermarkData);
    }
    
    /**
     * Draw text at specified position (no rotation)
     */
    private function drawTextAtPosition(\Imagick $image, \ImagickDraw $draw, string $text, array $position, array $watermarkData): void
    {
        $watermarkPosition = $watermarkData['watermark_position'] ?? 'bottom-right';
        $fontSize = $watermarkData['watermark_font_size'] ?? 24;
        $imageWidth = $image->getImageWidth();
        $imageHeight = $image->getImageHeight();
        
        // Adjust Y position based on watermark position
        if (strpos($watermarkPosition, 'top') !== false) {
            // For top positions, ensure text is fully visible (add font size to Y)
            $y = $position['y'] + $fontSize;
        } elseif (strpos($watermarkPosition, 'bottom') !== false) {
            // For bottom positions, position text so it's fully visible at bottom
            // annotateImage() positions from baseline, so we need to account for font size
            $y = $imageHeight - $fontSize;
        } else {
            // For center positions, use calculated position
            $y = $position['y'];
        }
        
        // Ensure text stays within bounds
        $x = max(0, min($position['x'], $imageWidth - 1));
        $y = max(0, min($y, $imageHeight - 1));
        
        // Apply text decoration if specified (before drawing text)
        $this->applyTextDecoration($draw, $watermarkData);
        
        $image->annotateImage($draw, $x, $y, 0, $text);
    }
    
    /**
     * Draw rotated text using temporary image
     */
    private function drawRotatedText(\Imagick $image, \ImagickDraw $draw, string $text, int $rotation, array $watermarkData): void
    {
        $watermarkPosition = $watermarkData['watermark_position'] ?? 'bottom-right';
        $offsetX = $watermarkData['watermark_offset_x'] ?? 10;
        $offsetY = $watermarkData['watermark_offset_y'] ?? 10;
        $fontSize = $watermarkData['watermark_font_size'] ?? 24;
        $imageWidth = $image->getImageWidth();
        $imageHeight = $image->getImageHeight();
        
        // Get text dimensions
        $textMetrics = $image->queryFontMetrics($draw, $text);
        $textWidth = (int) $textMetrics['textWidth'];
        $textHeight = (int) $textMetrics['textHeight'];
        
        // Create temporary image for text
        $tempImage = new \Imagick();
        $tempImage->newImage($textWidth + 20, $textHeight + 20, 'transparent');
        $tempImage->setImageFormat('png');
        
        // Create temporary draw object
        $tempDraw = new \ImagickDraw();
        $tempDraw->setFontSize($fontSize);
        $tempDraw->setFillColor($draw->getFillColor());
        $tempDraw->setFillOpacity($draw->getFillOpacity());
        $tempDraw->setFont($draw->getFont());
        
        // Apply text decoration to the temporary draw object (before drawing text)
        $this->applyTextDecoration($tempDraw, $watermarkData);
        
        // Draw text on temporary image
        $tempImage->annotateImage($tempDraw, 10, $textHeight + 10, 0, $text);
        
        // Rotate the temporary image
        $tempImage->rotateImage('transparent', $rotation);
        
        // Get rotated image dimensions
        $rotatedWidth = $tempImage->getImageWidth();
        $rotatedHeight = $tempImage->getImageHeight();
        
        // Calculate position based on watermark position and rotated text dimensions
        $x = 0;
        $y = 0;
        
        switch ($watermarkPosition) {
            case 'top-left':
                $x = $offsetX;
                $y = $offsetY;
                break;
            case 'top-center':
                $x = ($imageWidth - $rotatedWidth) / 2;
                $y = $offsetY;
                break;
            case 'top-right':
                $x = $imageWidth - $rotatedWidth - $offsetX;
                $y = $offsetY;
                break;
            case 'center-left':
                $x = $offsetX;
                $y = ($imageHeight - $rotatedHeight) / 2;
                break;
            case 'center':
                $x = ($imageWidth - $rotatedWidth) / 2;
                $y = ($imageHeight - $rotatedHeight) / 2;
                break;
            case 'center-right':
                $x = $imageWidth - $rotatedWidth - $offsetX;
                $y = ($imageHeight - $rotatedHeight) / 2;
                break;
            case 'bottom-left':
                $x = $offsetX;
                $y = $imageHeight - $rotatedHeight;
                break;
            case 'bottom-center':
                $x = ($imageWidth - $rotatedWidth) / 2;
                $y = $imageHeight - $rotatedHeight;
                break;
            case 'bottom-right':
            default:
                $x = $imageWidth - $rotatedWidth - $offsetX;
                $y = $imageHeight - $rotatedHeight;
                break;
        }
        
        // Ensure text stays within bounds
        $x = max(0, min($x, $imageWidth - $rotatedWidth));
        $y = max(0, min($y, $imageHeight - $rotatedHeight));
        
        // Composite rotated text onto main image (cast to int to avoid deprecation warning)
        $image->compositeImage($tempImage, \Imagick::COMPOSITE_OVER, (int)$x, (int)$y);
        
        // Clean up
        $tempImage->destroy();
        $tempDraw->destroy();
    }
    
    /**
     * Apply image watermark
     */
    private function applyImageWatermark(\Imagick $image, array $watermarkData): void
    {
        
        $watermarkPath = $watermarkData['watermark_image_path'] ?? '';
        
        if (empty($watermarkPath) || !file_exists($watermarkPath)) {
            return;
        }
        
        $watermarkImage = new \Imagick($watermarkPath);
        if (!$watermarkImage) {
            return;
        }
        
        $rotation = $watermarkData['watermark_rotation'] ?? 0;
        $opacity = $watermarkData['watermark_opacity'] ?? 50;
        
        
        
        // Apply rotation and positioning (opacity will be handled in the rotation method)
        $this->applyImageWatermarkWithRotation($image, $watermarkImage, $rotation, $opacity, $watermarkData);
        
        $watermarkImage->destroy();
    }
    
    /**
     * Apply image watermark with rotation
     */
    private function applyImageWatermarkWithRotation(\Imagick $image, \Imagick $watermarkImage, int $rotation, int $opacity, array $watermarkData): void
    {
        $watermarkPosition = $watermarkData['watermark_position'] ?? 'bottom-right';
        $offsetX = $watermarkData['watermark_offset_x'] ?? 10;
        $offsetY = $watermarkData['watermark_offset_y'] ?? 10;
        $imageWidth = $image->getImageWidth();
        $imageHeight = $image->getImageHeight();
        
        // Apply size scaling to watermark
        $watermarkImage = $this->applyWatermarkSizeScaling($watermarkImage, $watermarkData, $imageWidth, $imageHeight);
        
        
        // If no rotation, use simple positioning
        if ($rotation == 0) {
            $watermarkWidth = $watermarkImage->getImageWidth();
            $watermarkHeight = $watermarkImage->getImageHeight();
            $position = $this->calculatePosition($watermarkData, $imageWidth, $imageHeight, $watermarkWidth, $watermarkHeight);
            
            
            // Apply opacity using composite with alpha
            if ($opacity < 100) {
                $watermarkImage->setImageAlphaChannel(\Imagick::ALPHACHANNEL_ACTIVATE);
                $watermarkImage->evaluateImage(\Imagick::EVALUATE_MULTIPLY, $opacity / 100, \Imagick::CHANNEL_ALPHA);
            }
            
            
            // Check if position is within bounds
            if ($position['x'] < 0 || $position['y'] < 0 || 
                $position['x'] + $watermarkWidth > $imageWidth || 
                $position['y'] + $watermarkHeight > $imageHeight) {
            }
            
            // Check watermark image properties before compositing
            
            $compositeResult = $image->compositeImage($watermarkImage, \Imagick::COMPOSITE_OVER, $position['x'], $position['y']);
            return;
        }
        
        // For rotated image, we need to rotate the watermark first
        $rotatedWatermark = clone $watermarkImage;
        $rotatedWatermark->rotateImage('transparent', $rotation);
        
        // Apply opacity to rotated watermark
        if ($opacity < 100) {
            $rotatedWatermark->setImageAlphaChannel(\Imagick::ALPHACHANNEL_ACTIVATE);
            $rotatedWatermark->evaluateImage(\Imagick::EVALUATE_MULTIPLY, $opacity / 100, \Imagick::CHANNEL_ALPHA);
        }
        
        // Get rotated watermark dimensions
        $rotatedWidth = $rotatedWatermark->getImageWidth();
        $rotatedHeight = $rotatedWatermark->getImageHeight();
        
        
        // Calculate position based on watermark position and rotated dimensions
        $x = 0;
        $y = 0;
        
        switch ($watermarkPosition) {
            case 'top-left':
                $x = $offsetX;
                $y = $offsetY;
                break;
            case 'top-center':
                $x = ($imageWidth - $rotatedWidth) / 2;
                $y = $offsetY;
                break;
            case 'top-right':
                $x = $imageWidth - $rotatedWidth - $offsetX;
                $y = $offsetY;
                break;
            case 'center-left':
                $x = $offsetX;
                $y = ($imageHeight - $rotatedHeight) / 2;
                break;
            case 'center':
                $x = ($imageWidth - $rotatedWidth) / 2;
                $y = ($imageHeight - $rotatedHeight) / 2;
                break;
            case 'center-right':
                $x = $imageWidth - $rotatedWidth - $offsetX;
                $y = ($imageHeight - $rotatedHeight) / 2;
                break;
            case 'bottom-left':
                $x = $offsetX;
                $y = $imageHeight - $rotatedHeight;
                break;
            case 'bottom-center':
                $x = ($imageWidth - $rotatedWidth) / 2;
                $y = $imageHeight - $rotatedHeight;
                break;
            case 'bottom-right':
            default:
                $x = $imageWidth - $rotatedWidth - $offsetX;
                $y = $imageHeight - $rotatedHeight;
                break;
        }
        
        // Ensure watermark stays within bounds
        $x = max(0, min($x, $imageWidth - $rotatedWidth));
        $y = max(0, min($y, $imageHeight - $rotatedHeight));
        
        
        // Composite rotated watermark onto main image (cast to int to avoid deprecation warning)
        $image->compositeImage($rotatedWatermark, \Imagick::COMPOSITE_OVER, (int)$x, (int)$y);
        
        // Clean up rotated watermark
        $rotatedWatermark->destroy();
    }
    
    /**
     * Apply watermark size scaling
     */
    private function applyWatermarkSizeScaling(\Imagick $watermarkImage, array $watermarkData, int $imageWidth, int $imageHeight): \Imagick
    {
        $sizeType = $watermarkData['watermark_size_type'] ?? 'original';
        $originalWidth = $watermarkImage->getImageWidth();
        $originalHeight = $watermarkImage->getImageHeight();
        
        
        $newWidth = $originalWidth;
        $newHeight = $originalHeight;
        
        switch ($sizeType) {
            case 'scaled':
                $scalePercentage = $watermarkData['watermark_scale_percentage'] ?? 80;
                $newWidth = (int) ($imageWidth * $scalePercentage / 100);
                $newHeight = (int) ($originalHeight * $newWidth / $originalWidth); // Maintain aspect ratio
                
                // Ensure watermark fits within image bounds
                if ($newHeight > $imageHeight) {
                    $newHeight = $imageHeight;
                    $newWidth = (int) ($originalWidth * $newHeight / $originalHeight);
                }
                break;
                
            case 'custom':
                $newWidth = $watermarkData['watermark_custom_width'] ?? 100;
                $newHeight = $watermarkData['watermark_custom_height'] ?? 100;
                
                // Ensure watermark fits within image bounds
                if ($newWidth > $imageWidth) {
                    $newWidth = $imageWidth;
                }
                if ($newHeight > $imageHeight) {
                    $newHeight = $imageHeight;
                }
                break;
                
            case 'original':
            default:
                // Even for original size, ensure it fits within image bounds
                if ($originalWidth > $imageWidth || $originalHeight > $imageHeight) {
                    $scaleX = $imageWidth / $originalWidth;
                    $scaleY = $imageHeight / $originalHeight;
                    $scale = min($scaleX, $scaleY); // Use the smaller scale to fit both dimensions
                    
                    $newWidth = (int) ($originalWidth * $scale);
                    $newHeight = (int) ($originalHeight * $scale);
                } else {
                    return $watermarkImage; // No scaling needed
                }
                break;
        }
        
        // Create scaled watermark image
        $scaledWatermark = clone $watermarkImage;
        
        // Scale the watermark
        $scaledWatermark->resizeImage($newWidth, $newHeight, \Imagick::FILTER_LANCZOS, 1);
        
        // Destroy original watermark image
        $watermarkImage->destroy();
        
        return $scaledWatermark;
    }
    
    /**
     * Calculate watermark position
     */
    private function calculatePosition(array $watermarkData, int $imageWidth, int $imageHeight, int $watermarkWidth, int $watermarkHeight): array
    {
        $position = $watermarkData['watermark_position'] ?? 'bottom-right';
        $offsetX = $watermarkData['watermark_offset_x'] ?? 10;
        $offsetY = $watermarkData['watermark_offset_y'] ?? 10;
        
        $x = 0;
        $y = 0;
        
        switch ($position) {
            case 'top-left':
                $x = $offsetX;
                $y = $offsetY;
                break;
            case 'top-center':
                $x = ($imageWidth - $watermarkWidth) / 2;
                $y = $offsetY;
                break;
            case 'top-right':
                $x = $imageWidth - $watermarkWidth - $offsetX;
                $y = $offsetY;
                break;
            case 'center-left':
                $x = $offsetX;
                $y = ($imageHeight - $watermarkHeight) / 2;
                break;
            case 'center':
                $x = ($imageWidth - $watermarkWidth) / 2;
                $y = ($imageHeight - $watermarkHeight) / 2;
                break;
            case 'center-right':
                $x = $imageWidth - $watermarkWidth - $offsetX;
                $y = ($imageHeight - $watermarkHeight) / 2;
                break;
            case 'bottom-left':
                $x = $offsetX;
                $y = $imageHeight - $watermarkHeight;
                break;
            case 'bottom-center':
                $x = ($imageWidth - $watermarkWidth) / 2;
                $y = $imageHeight - $watermarkHeight;
                break;
            case 'bottom-right':
            default:
                $x = $imageWidth - $watermarkWidth - $offsetX;
                $y = $imageHeight - $watermarkHeight;
                break;
        }
        
        return ['x' => (int)$x, 'y' => (int)$y];
    }
    
    /**
     * Apply text decoration to ImagickDraw object (underline, overline, line-through)
     */
    private function applyTextDecoration(\ImagickDraw $draw, array $watermarkData): void
    {
        $textDecoration = $watermarkData['watermark_text_decoration'] ?? 'none';
        
        
        if ($textDecoration === 'none') {
            return;
        }
        
        // Use Imagick's native text decoration support
        switch ($textDecoration) {
            case 'underline':
                $draw->setTextDecoration(\Imagick::DECORATION_UNDERLINE);
                break;
            case 'overline':
                $draw->setTextDecoration(\Imagick::DECORATION_OVERLINE);
                break;
            case 'line-through':
                $draw->setTextDecoration(\Imagick::DECORATION_LINETHROUGH);
                break;
        }
        
    }
    
    
    /**
     * Get system font name for Imagick with weight and style
     */
    private function getSystemFontName(string $fontFamily, string $fontWeight = 'normal', string $fontStyle = 'normal'): string
    {
        // Map font family names to system font names that Imagick can use
        $fontMap = [
            'Arial' => [
                'normal' => [
                    'normal' => 'Arial',
                    'italic' => 'Arial-Italic',
                    'oblique' => 'Arial-Italic'  // Fallback to Italic since Oblique is not available
                ],
                'bold' => [
                    'normal' => 'Arial-Bold',
                    'italic' => 'Arial-BoldItalic',
                    'oblique' => 'Arial-BoldItalic'  // Fallback to BoldItalic since BoldOblique is not available
                ],
                'lighter' => [
                    'normal' => 'Arial', // Fallback to regular Arial for lighter
                    'italic' => 'Arial-Italic',
                    'oblique' => 'Arial-Italic'  // Fallback to Italic since Oblique is not available
                ]
            ],
            'Helvetica' => [
                'normal' => [
                    'normal' => 'Helvetica',
                    'italic' => 'Helvetica-Oblique'
                ],
                'bold' => [
                    'normal' => 'Helvetica-Bold',
                    'italic' => 'Helvetica-BoldOblique'
                ]
            ],
            'Times New Roman' => [
                'normal' => [
                    'normal' => 'Times-Roman',
                    'italic' => 'Times-Italic'
                ],
                'bold' => [
                    'normal' => 'Times-Bold',
                    'italic' => 'Times-BoldItalic'
                ]
            ],
            'Georgia' => [
                'normal' => [
                    'normal' => 'Georgia',
                    'italic' => 'Georgia-Italic'
                ],
                'bold' => [
                    'normal' => 'Georgia-Bold',
                    'italic' => 'Georgia-BoldItalic'
                ]
            ],
            'Verdana' => [
                'normal' => [
                    'normal' => 'Verdana',
                    'italic' => 'Verdana-Italic'
                ],
                'bold' => [
                    'normal' => 'Verdana-Bold',
                    'italic' => 'Verdana-BoldItalic'
                ]
            ],
            'Courier New' => [
                'normal' => [
                    'normal' => 'Courier',
                    'italic' => 'Courier-Oblique'
                ],
                'bold' => [
                    'normal' => 'Courier-Bold',
                    'italic' => 'Courier-BoldOblique'
                ]
            ]
        ];
        
        $systemFontName = $fontMap[$fontFamily][$fontWeight][$fontStyle] ?? 
                         $fontMap[$fontFamily]['normal']['normal'] ?? 
                         'Arial'; // Ultimate fallback
        
        
        return $systemFontName;
    }
    
    /**
     * Convert hex color to RGB
     */
    private function hexToRgb(string $hex): string
    {
        $hex = ltrim($hex, '#');
        if (strlen($hex) == 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));
        
        return "rgb($r,$g,$b)";
    }
    
    /**
     * Save image to file
     */
    public function saveImage(\Imagick $image, string $outputPath, array $watermarkData = []): bool
    {
        
        $extension = strtolower(pathinfo($outputPath, PATHINFO_EXTENSION));
        $quality = $watermarkData['watermark_quality'] ?? 90;
        $imageFormat = $watermarkData['image_format'] ?? 'baseline';
        
        
        
        switch ($extension) {
            case 'jpg':
            case 'jpeg':
                $image->setImageFormat('jpeg');
                $image->setImageCompressionQuality($quality);
                
                // Set progressive JPEG if requested
                if ($imageFormat === 'progressive') {
                    $image->setInterlaceScheme(\Imagick::INTERLACE_JPEG);
                } else {
                    $image->setInterlaceScheme(\Imagick::INTERLACE_NO);
                }
                break;
                
            case 'png':
                $image->setImageFormat('png');
                // PNG compression level (0-9, where 9 is maximum compression)
                $compression = (int) ((100 - $quality) / 10);
                $compression = max(0, min(9, $compression));
                $image->setImageCompressionQuality($compression * 10); // Convert to 0-100 scale
                break;
                
            case 'gif':
                $image->setImageFormat('gif');
                break;
                
            case 'webp':
                $image->setImageFormat('webp');
                $image->setImageCompressionQuality($quality);
                break;
                
            default:
                return false;
        }
        
        $result = $image->writeImage($outputPath);
        
        if ($result) {
            if (file_exists($outputPath)) {
            } else {
            }
        }
        
        return $result;
    }
    
    /**
     * Check if processor is available
     */
    public function isAvailable(): bool
    {
        return extension_loaded('imagick');
    }
}