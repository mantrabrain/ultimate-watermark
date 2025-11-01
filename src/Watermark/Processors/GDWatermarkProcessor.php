<?php

namespace MantraBrain\UltimateWatermark\Watermark\Processors;

use MantraBrain\UltimateWatermark\Watermark\WatermarkProcessorInterface;

class GDWatermarkProcessor implements WatermarkProcessorInterface
{
    /**
     * Apply watermark to image
     */
    public function applyWatermark(string $sourceImagePath, string $outputImagePath, array $watermarkData): bool
    {
        
        // Load source image
        $image = $this->loadImage($sourceImagePath);
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
        imagedestroy($image);
        
        return $result;
    }
    
    /**
     * Scale watermark data proportionally based on current image size vs full size (original)
     * This ensures watermarks look correct on different image sizes (thumbnails, medium, full, etc.)
     * The watermark is configured for the FULL SIZE image, and we scale DOWN for smaller sizes
     */
    private function scaleWatermarkDataForImage(\GdImage $image, array $watermarkData): array
    {
        // Get current image dimensions
        $currentWidth = imagesx($image);
        $currentHeight = imagesy($image);
        
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
        $image = $this->loadImage($sourceImagePath);
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
        imagedestroy($image);
        
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
     * Load image from file
     */
    private function loadImage(string $path): \GdImage|false
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        
        switch ($extension) {
            case 'jpg':
            case 'jpeg':
                return imagecreatefromjpeg($path);
            case 'png':
                return imagecreatefrompng($path);
            case 'gif':
                return imagecreatefromgif($path);
            case 'webp':
                return imagecreatefromwebp($path);
            default:
                return false;
        }
    }
    
    /**
     * Apply text watermark - Simple and clean implementation
     */
    private function applyTextWatermark(\GdImage $image, array $watermarkData): void
    {
        $text = $watermarkData['watermark_text'] ?? 'Watermark';
        $fontSize = $watermarkData['watermark_font_size'] ?? 24;
        $color = $this->hexToRgb($watermarkData['watermark_color'] ?? '#000000');
        $opacity = $watermarkData['watermark_opacity'] ?? 50;
        $rotation = $watermarkData['watermark_rotation'] ?? 0;
        
        $imageWidth = imagesx($image);
        $imageHeight = imagesy($image);
        
        // Create color with opacity
        $textColor = imagecolorallocatealpha($image, $color['r'], $color['g'], $color['b'], 127 - ($opacity * 127 / 100));
        
        // Draw text with rotation (position will be calculated inside based on rotation)
        $this->drawTextWithRotation($image, $text, $textColor, $fontSize, $rotation, $watermarkData);
    }
    
    /**
     * Draw text with rotation
     */
    private function drawTextWithRotation(\GdImage $image, string $text, int $textColor, int $fontSize, int $rotation, array $watermarkData): void
    {
        $watermarkPosition = $watermarkData['watermark_position'] ?? 'bottom-right';
        $imageWidth = imagesx($image);
        $imageHeight = imagesy($image);
        
        // If no rotation, use simple positioning
        if ($rotation == 0) {
            $fontFamily = $watermarkData['watermark_font_family'] ?? 'Arial';
            $fontWeight = $watermarkData['watermark_font_weight'] ?? 'normal';
            $fontStyle = $watermarkData['watermark_font_style'] ?? 'normal';
            $fontPath = $this->getFontPath($fontFamily, $fontWeight, $fontStyle);
            
            if ($fontPath && function_exists('imagettfbbox')) {
                // Use TTF font for accurate text dimensions
                $bbox = imagettfbbox($fontSize, 0, $fontPath, $text);
                $textWidth = $bbox[4] - $bbox[0];
                $textHeight = $bbox[1] - $bbox[5];
            } else {
                // Fallback to estimated dimensions
                $textWidth = (int) round($fontSize * strlen($text) * 0.6);
                $textHeight = (int) $fontSize;
            }
            
            $position = $this->calculatePosition($watermarkData, $imageWidth, $imageHeight, $textWidth, $textHeight);
            $this->drawTextAtPosition($image, $text, $position, $textColor, $fontSize, $watermarkData);
            return;
        }
        
        // For rotated text, we need to use imagettftext with a TTF font
        $fontFamily = $watermarkData['watermark_font_family'] ?? 'Arial';
        $fontWeight = $watermarkData['watermark_font_weight'] ?? 'normal';
        $fontStyle = $watermarkData['watermark_font_style'] ?? 'normal';
        $fontPath = $this->getFontPath($fontFamily, $fontWeight, $fontStyle);
        
        
        if ($fontPath && function_exists('imagettftext')) {
            $this->drawRotatedTextTTF($image, $text, $textColor, $fontSize, $rotation, $fontPath, $watermarkData);
        } else {
            // Fallback to basic rotation using imagestring
            $this->drawRotatedTextBasic($image, $text, $textColor, $fontSize, $rotation, $watermarkData);
        }
    }
    
    /**
     * Draw text at specified position (no rotation)
     */
    private function drawTextAtPosition(\GdImage $image, string $text, array $position, int $textColor, int $fontSize, array $watermarkData): void
    {
        $watermarkPosition = $watermarkData['watermark_position'] ?? 'bottom-right';
        $imageWidth = imagesx($image);
        $imageHeight = imagesy($image);
        
        // Adjust Y position based on watermark position
        if (strpos($watermarkPosition, 'top') !== false) {
            // For top positions, ensure text is fully visible (add font size to Y)
            $y = $position['y'] + $fontSize;
        } elseif (strpos($watermarkPosition, 'bottom') !== false) {
            // For bottom positions, position text so it's fully visible at bottom
            // imagestring() positions from top-left, so we need to account for font size
            $y = $imageHeight - $fontSize;
        } else {
            // For center positions, use calculated position
            $y = $position['y'];
        }
        
        // Try to use TTF font first
        $fontFamily = $watermarkData['watermark_font_family'] ?? 'Arial';
        $fontWeight = $watermarkData['watermark_font_weight'] ?? 'normal';
        $fontStyle = $watermarkData['watermark_font_style'] ?? 'normal';
        $fontPath = $this->getFontPath($fontFamily, $fontWeight, $fontStyle);
        
        if ($fontPath && function_exists('imagettftext')) {
            // Adjust Y position based on watermark position for TTF fonts
            if (strpos($watermarkPosition, 'top') !== false) {
                // For top positions, ensure text is fully visible
                $y = $position['y'] + $fontSize;
            } elseif (strpos($watermarkPosition, 'bottom') !== false) {
                // For bottom positions, position text so it's fully visible at bottom
                $y = $imageHeight - 5; // Small margin from bottom
            } else {
                // For center positions, use calculated position
                $y = $position['y'] + $fontSize; // TTF fonts need baseline adjustment
            }
            
            // Ensure text stays within bounds
            $x = max(0, min($position['x'], $imageWidth - 1));
            $y = max(0, min($y, $imageHeight - 1));
            
            imagettftext($image, $fontSize, 0, $x, $y, $textColor, $fontPath, $text);
            
            // Draw text decoration if specified
            $this->drawTextDecoration($image, $text, $x, $y, $fontSize, $textColor, $fontPath, $watermarkData);
        } else {
            // Fallback to built-in font (limited color support)
            // Ensure text stays within bounds
            $x = max(0, min($position['x'], $imageWidth - 1));
            $y = max(0, min($y, $imageHeight - 1));
            
            // imagestring() doesn't support custom colors, so we'll use a default color
            imagestring($image, 5, $x, $y, $text);
        }
    }
    
    /**
     * Draw text decoration (underline, overline, line-through)
     */
    private function drawTextDecoration(\GdImage $image, string $text, int $x, int $y, int $fontSize, int $textColor, string $fontPath, array $watermarkData): void
    {
        $textDecoration = $watermarkData['watermark_text_decoration'] ?? 'none';
        
        
        if ($textDecoration === 'none') {
            return;
        }
        
        // Get text dimensions
        $bbox = imagettfbbox($fontSize, 0, $fontPath, $text);
        $textWidth = $bbox[4] - $bbox[0];
        $textHeight = $bbox[1] - $bbox[5];
        
        // Calculate decoration line position and thickness
        $lineThickness = max(1, (int) ($fontSize / 20)); // Line thickness based on font size
        $lineY = $y;
        
        switch ($textDecoration) {
            case 'underline':
                $lineY = $y + 2; // Below the text baseline
                break;
            case 'overline':
                $lineY = $y - $textHeight - 2; // Above the text
                break;
            case 'line-through':
                $lineY = $y - ($textHeight / 2); // Through the middle of the text
                break;
        }
        
        // Draw the decoration line
        for ($i = 0; $i < $lineThickness; $i++) {
            imageline($image, $x, $lineY + $i, $x + $textWidth, $lineY + $i, $textColor);
        }
        
    }
    
    /**
     * Draw rotated text using TTF font
     */
    private function drawRotatedTextTTF(\GdImage $image, string $text, int $textColor, int $fontSize, int $rotation, string $fontPath, array $watermarkData): void
    {
        $watermarkPosition = $watermarkData['watermark_position'] ?? 'bottom-right';
        $offsetX = $watermarkData['watermark_offset_x'] ?? 10;
        $offsetY = $watermarkData['watermark_offset_y'] ?? 10;
        $imageWidth = imagesx($image);
        $imageHeight = imagesy($image);
        
        // Get text bounding box for rotated text
        $bbox = imagettfbbox($fontSize, $rotation, $fontPath, $text);
        $textWidth = $bbox[4] - $bbox[0];
        $textHeight = $bbox[1] - $bbox[5];
        
        // Calculate position based on watermark position and rotated text dimensions
        $x = 0;
        $y = 0;
        
        switch ($watermarkPosition) {
            case 'top-left':
                $x = $offsetX;
                $y = $offsetY + $textHeight;
                break;
            case 'top-center':
                $x = ($imageWidth - $textWidth) / 2;
                $y = $offsetY + $textHeight;
                break;
            case 'top-right':
                $x = $imageWidth - $textWidth - $offsetX;
                $y = $offsetY + $textHeight;
                break;
            case 'center-left':
                $x = $offsetX;
                $y = ($imageHeight + $textHeight) / 2;
                break;
            case 'center':
                $x = ($imageWidth - $textWidth) / 2;
                $y = ($imageHeight + $textHeight) / 2;
                break;
            case 'center-right':
                $x = $imageWidth - $textWidth - $offsetX;
                $y = ($imageHeight + $textHeight) / 2;
                break;
            case 'bottom-left':
                $x = $offsetX;
                $y = $imageHeight - $textHeight;
                break;
            case 'bottom-center':
                $x = ($imageWidth - $textWidth) / 2;
                $y = $imageHeight - $textHeight;
                break;
            case 'bottom-right':
            default:
                $x = $imageWidth - $textWidth - $offsetX;
                $y = $imageHeight - $textHeight;
                break;
        }
        
        // Ensure text stays within bounds
        $x = max(0, min($x, $imageWidth - 1));
        $y = max(0, min($y, $imageHeight - 1));
        
        imagettftext($image, $fontSize, $rotation, $x, $y, $textColor, $fontPath, $text);
        
        // Draw text decoration for rotated text (simplified)
        $this->drawTextDecoration($image, $text, $x, $y, $fontSize, $textColor, $fontPath, $watermarkData);
    }
    
    /**
     * Draw rotated text using basic method (fallback)
     */
    private function drawRotatedTextBasic(\GdImage $image, string $text, int $textColor, int $fontSize, int $rotation, array $watermarkData): void
    {
        $watermarkPosition = $watermarkData['watermark_position'] ?? 'bottom-right';
        $offsetX = $watermarkData['watermark_offset_x'] ?? 10;
        $offsetY = $watermarkData['watermark_offset_y'] ?? 10;
        $imageWidth = imagesx($image);
        $imageHeight = imagesy($image);
        
        // For basic rotation, we'll create a temporary image with the text
        // and then rotate it using imagerotate
        $tempWidth = $fontSize * strlen($text) + 20;
        $tempHeight = $fontSize + 20;
        
        $tempImage = imagecreatetruecolor($tempWidth, $tempHeight);
        $transparent = imagecolorallocatealpha($tempImage, 0, 0, 0, 127);
        imagefill($tempImage, 0, 0, $transparent);
        imagesavealpha($tempImage, true);
        
        // Draw text on temporary image
        imagestring($tempImage, 5, 10, 10, $text, $textColor);
        
        // Rotate the temporary image
        $rotatedImage = imagerotate($tempImage, $rotation, $transparent);
        
        // Get rotated image dimensions
        $rotatedWidth = imagesx($rotatedImage);
        $rotatedHeight = imagesy($rotatedImage);
        
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
        
        // Copy rotated text to main image (cast to int to avoid deprecation warning)
        imagecopy($image, $rotatedImage, (int)$x, (int)$y, 0, 0, $rotatedWidth, $rotatedHeight);
        
        // Clean up
        imagedestroy($tempImage);
        imagedestroy($rotatedImage);
    }
    
    /**
     * Get font path for given font family with weight and style
     */
    private function getFontPath(string $fontFamily, string $fontWeight = 'normal', string $fontStyle = 'normal'): ?string
    {
        // Map font family names to actual font file paths with weight and style variants
        $fontMap = [
            'Arial' => [
                'normal' => [
                    'normal' => [
                        '/System/Library/Fonts/Supplemental/Arial.ttf', // macOS
                        '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf', // Linux
                        '/usr/share/fonts/TTF/arial.ttf', // Linux
                        'C:\Windows\Fonts\arial.ttf', // Windows
                    ],
                    'italic' => [
                        '/System/Library/Fonts/Supplemental/Arial Italic.ttf', // macOS
                        '/usr/share/fonts/truetype/dejavu/DejaVuSans-Oblique.ttf', // Linux
                        'C:\Windows\Fonts\ariali.ttf', // Windows
                    ]
                ],
                'bold' => [
                    'normal' => [
                        '/System/Library/Fonts/Supplemental/Arial Bold.ttf', // macOS
                        '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf', // Linux
                        'C:\Windows\Fonts\arialbd.ttf', // Windows
                    ],
                    'italic' => [
                        '/System/Library/Fonts/Supplemental/Arial Bold Italic.ttf', // macOS
                        '/usr/share/fonts/truetype/dejavu/DejaVuSans-BoldOblique.ttf', // Linux
                        'C:\Windows\Fonts\arialbi.ttf', // Windows
                    ]
                ],
                'lighter' => [
                    'normal' => [
                        '/System/Library/Fonts/Supplemental/Arial.ttf', // macOS (fallback to normal)
                        '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf', // Linux
                        'C:\Windows\Fonts\arial.ttf', // Windows
                    ]
                ]
            ],
            'Times New Roman' => [
                'normal' => [
                    'normal' => [
                        '/System/Library/Fonts/Supplemental/Times New Roman.ttf', // macOS
                        '/usr/share/fonts/truetype/liberation/LiberationSerif-Regular.ttf', // Linux
                        'C:\Windows\Fonts\times.ttf', // Windows
                    ],
                    'italic' => [
                        '/System/Library/Fonts/Supplemental/Times New Roman Italic.ttf', // macOS
                        '/usr/share/fonts/truetype/liberation/LiberationSerif-Italic.ttf', // Linux
                        'C:\Windows\Fonts\timesi.ttf', // Windows
                    ]
                ],
                'bold' => [
                    'normal' => [
                        '/System/Library/Fonts/Supplemental/Times New Roman Bold.ttf', // macOS
                        '/usr/share/fonts/truetype/liberation/LiberationSerif-Bold.ttf', // Linux
                        'C:\Windows\Fonts\timesbd.ttf', // Windows
                    ],
                    'italic' => [
                        '/System/Library/Fonts/Supplemental/Times New Roman Bold Italic.ttf', // macOS
                        '/usr/share/fonts/truetype/liberation/LiberationSerif-BoldItalic.ttf', // Linux
                        'C:\Windows\Fonts\timesbi.ttf', // Windows
                    ]
                ]
            ],
            'Georgia' => [
                'normal' => [
                    'normal' => [
                        '/System/Library/Fonts/Supplemental/Georgia.ttf', // macOS
                        '/usr/share/fonts/truetype/liberation/LiberationSerif-Regular.ttf', // Linux
                        'C:\Windows\Fonts\georgia.ttf', // Windows
                    ],
                    'italic' => [
                        '/System/Library/Fonts/Supplemental/Georgia Italic.ttf', // macOS
                        '/usr/share/fonts/truetype/liberation/LiberationSerif-Italic.ttf', // Linux
                        'C:\Windows\Fonts\georgiai.ttf', // Windows
                    ]
                ],
                'bold' => [
                    'normal' => [
                        '/System/Library/Fonts/Supplemental/Georgia Bold.ttf', // macOS
                        '/usr/share/fonts/truetype/liberation/LiberationSerif-Bold.ttf', // Linux
                        'C:\Windows\Fonts\georgiab.ttf', // Windows
                    ],
                    'italic' => [
                        '/System/Library/Fonts/Supplemental/Georgia Bold Italic.ttf', // macOS
                        '/usr/share/fonts/truetype/liberation/LiberationSerif-BoldItalic.ttf', // Linux
                        'C:\Windows\Fonts\georgiaz.ttf', // Windows
                    ]
                ]
            ],
            'Verdana' => [
                'normal' => [
                    'normal' => [
                        '/System/Library/Fonts/Supplemental/Verdana.ttf', // macOS
                        '/usr/share/fonts/truetype/liberation/LiberationSans-Regular.ttf', // Linux
                        'C:\Windows\Fonts\verdana.ttf', // Windows
                    ],
                    'italic' => [
                        '/System/Library/Fonts/Supplemental/Verdana Italic.ttf', // macOS
                        '/usr/share/fonts/truetype/liberation/LiberationSans-Italic.ttf', // Linux
                        'C:\Windows\Fonts\verdanai.ttf', // Windows
                    ]
                ],
                'bold' => [
                    'normal' => [
                        '/System/Library/Fonts/Supplemental/Verdana Bold.ttf', // macOS
                        '/usr/share/fonts/truetype/liberation/LiberationSans-Bold.ttf', // Linux
                        'C:\Windows\Fonts\verdanab.ttf', // Windows
                    ],
                    'italic' => [
                        '/System/Library/Fonts/Supplemental/Verdana Bold Italic.ttf', // macOS
                        '/usr/share/fonts/truetype/liberation/LiberationSans-BoldItalic.ttf', // Linux
                        'C:\Windows\Fonts\verdanaz.ttf', // Windows
                    ]
                ]
            ],
            'Helvetica' => [
                'normal' => [
                    'normal' => [
                        '/System/Library/Fonts/Helvetica.ttc', // macOS
                        '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf', // Linux
                        'C:\Windows\Fonts\arial.ttf', // Windows (fallback to Arial)
                    ]
                ],
                'bold' => [
                    'normal' => [
                        '/System/Library/Fonts/Helvetica.ttc', // macOS
                        '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf', // Linux
                        'C:\Windows\Fonts\arialbd.ttf', // Windows
                    ]
                ]
            ],
            'Courier New' => [
                'normal' => [
                    'normal' => [
                        '/System/Library/Fonts/Courier.ttc', // macOS
                        '/usr/share/fonts/truetype/liberation/LiberationMono-Regular.ttf', // Linux
                        'C:\Windows\Fonts\cour.ttf', // Windows
                    ],
                    'italic' => [
                        '/System/Library/Fonts/Courier.ttc', // macOS
                        '/usr/share/fonts/truetype/liberation/LiberationMono-Italic.ttf', // Linux
                        'C:\Windows\Fonts\couri.ttf', // Windows
                    ]
                ],
                'bold' => [
                    'normal' => [
                        '/System/Library/Fonts/Courier.ttc', // macOS
                        '/usr/share/fonts/truetype/liberation/LiberationMono-Bold.ttf', // Linux
                        'C:\Windows\Fonts\courbd.ttf', // Windows
                    ],
                    'italic' => [
                        '/System/Library/Fonts/Courier.ttc', // macOS
                        '/usr/share/fonts/truetype/liberation/LiberationMono-BoldItalic.ttf', // Linux
                        'C:\Windows\Fonts\courbi.ttf', // Windows
                    ]
                ]
            ]
        ];
        
        // Get font paths for the specific weight and style
        $fontPaths = $fontMap[$fontFamily][$fontWeight][$fontStyle] ?? 
                    $fontMap[$fontFamily]['normal']['normal'] ?? 
                    $fontMap['Arial']['normal']['normal']; // Ultimate fallback
        
        foreach ($fontPaths as $fontPath) {
            if (file_exists($fontPath)) {
                return $fontPath;
            }
        }
        
        return null;
    }
    
    /**
     * Get system font path (legacy method for backward compatibility)
     */
    private function getSystemFont(): ?string
    {
        return $this->getFontPath('Arial');
    }
    
    
    /**
     * Apply image watermark
     */
    private function applyImageWatermark(\GdImage $image, array $watermarkData): void
    {
        $watermarkPath = $watermarkData['watermark_image_path'] ?? '';
        if (empty($watermarkPath) || !file_exists($watermarkPath)) {
            return;
        }
        
        $watermarkImage = $this->loadWatermarkImage($watermarkPath);
        if (!$watermarkImage) {
            return;
        }
        
        $rotation = $watermarkData['watermark_rotation'] ?? 0;
        $opacity = $watermarkData['watermark_opacity'] ?? 50;
        
        
        // Apply rotation and positioning (opacity will be handled in the rotation method)
        $this->applyImageWatermarkWithRotation($image, $watermarkImage, $rotation, $opacity, $watermarkData);
        
        imagedestroy($watermarkImage);
    }
    
    /**
     * Apply image watermark with rotation
     */
    private function applyImageWatermarkWithRotation(\GdImage $image, \GdImage $watermarkImage, int $rotation, int $opacity, array $watermarkData): void
    {
        $watermarkPosition = $watermarkData['watermark_position'] ?? 'bottom-right';
        $offsetX = $watermarkData['watermark_offset_x'] ?? 10;
        $offsetY = $watermarkData['watermark_offset_y'] ?? 10;
        $imageWidth = imagesx($image);
        $imageHeight = imagesy($image);
        
        // Apply size scaling to watermark
        $watermarkImage = $this->applyWatermarkSizeScaling($watermarkImage, $watermarkData, $imageWidth, $imageHeight);
        
        
        // If no rotation, use simple positioning
        if ($rotation == 0) {
            $watermarkWidth = imagesx($watermarkImage);
            $watermarkHeight = imagesy($watermarkImage);
            $position = $this->calculatePosition($watermarkData, $imageWidth, $imageHeight, $watermarkWidth, $watermarkHeight);
            
            // Apply opacity using imagecopymerge for better performance
            if ($opacity < 100) {
                imagecopymerge($image, $watermarkImage, $position['x'], $position['y'], 0, 0, $watermarkWidth, $watermarkHeight, $opacity);
            } else {
                imagecopy($image, $watermarkImage, $position['x'], $position['y'], 0, 0, $watermarkWidth, $watermarkHeight);
            }
            return;
        }
        
        // For rotated image, we need to rotate the watermark first
        $rotatedWatermark = imagerotate($watermarkImage, $rotation, 0);
        if (!$rotatedWatermark) {
            // Fallback to non-rotated if rotation fails
            $watermarkWidth = imagesx($watermarkImage);
            $watermarkHeight = imagesy($watermarkImage);
            $position = $this->calculatePosition($watermarkData, $imageWidth, $imageHeight, $watermarkWidth, $watermarkHeight);
            
            if ($opacity < 100) {
                imagecopymerge($image, $watermarkImage, $position['x'], $position['y'], 0, 0, $watermarkWidth, $watermarkHeight, $opacity);
            } else {
                imagecopy($image, $watermarkImage, $position['x'], $position['y'], 0, 0, $watermarkWidth, $watermarkHeight);
            }
            return;
        }
        
        // Get rotated watermark dimensions
        $rotatedWidth = imagesx($rotatedWatermark);
        $rotatedHeight = imagesy($rotatedWatermark);
        
        
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
        
        
        // Copy rotated watermark to main image with opacity (cast to int to avoid deprecation warning)
        if ($opacity < 100) {
            imagecopymerge($image, $rotatedWatermark, (int)$x, (int)$y, 0, 0, $rotatedWidth, $rotatedHeight, $opacity);
        } else {
            imagecopy($image, $rotatedWatermark, (int)$x, (int)$y, 0, 0, $rotatedWidth, $rotatedHeight);
        }
        
        // Clean up rotated watermark
        imagedestroy($rotatedWatermark);
    }
    
    /**
     * Apply watermark size scaling
     */
    private function applyWatermarkSizeScaling(\GdImage $watermarkImage, array $watermarkData, int $imageWidth, int $imageHeight): \GdImage
    {
        $sizeType = $watermarkData['watermark_size_type'] ?? 'original';
        $originalWidth = imagesx($watermarkImage);
        $originalHeight = imagesy($watermarkImage);
        
        
        $newWidth = $originalWidth;
        $newHeight = $originalHeight;
        
        switch ($sizeType) {
            case 'scaled':
                $scalePercentage = $watermarkData['watermark_scale_percentage'] ?? 80;
                $newWidth = (int) ($imageWidth * $scalePercentage / 100);
                $newHeight = (int) ($originalHeight * $newWidth / $originalWidth); // Maintain aspect ratio
                break;
                
            case 'custom':
                $newWidth = $watermarkData['watermark_custom_width'] ?? 100;
                $newHeight = $watermarkData['watermark_custom_height'] ?? 100;
                break;
                
            case 'original':
            default:
                return $watermarkImage; // No scaling needed
        }
        
        // Create scaled watermark image
        $scaledWatermark = imagecreatetruecolor($newWidth, $newHeight);
        
        // Preserve transparency
        imagealphablending($scaledWatermark, false);
        imagesavealpha($scaledWatermark, true);
        $transparent = imagecolorallocatealpha($scaledWatermark, 0, 0, 0, 127);
        imagefill($scaledWatermark, 0, 0, $transparent);
        
        // Scale the watermark
        imagecopyresampled(
            $scaledWatermark, $watermarkImage,
            0, 0, 0, 0,
            $newWidth, $newHeight,
            $originalWidth, $originalHeight
        );
        
        // Destroy original watermark image
        imagedestroy($watermarkImage);
        
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
     * Load watermark image
     */
    private function loadWatermarkImage(string $path): \GdImage|false
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        
        switch ($extension) {
            case 'jpg':
            case 'jpeg':
                return imagecreatefromjpeg($path);
            case 'png':
                return imagecreatefrompng($path);
            case 'gif':
                return imagecreatefromgif($path);
            case 'webp':
                return imagecreatefromwebp($path);
            default:
                return false;
        }
    }
    
    /**
     * Apply opacity to image
     */
    private function applyOpacity(\GdImage $image, int $opacity): void
    {
        $width = imagesx($image);
        $height = imagesy($image);
        
        for ($x = 0; $x < $width; $x++) {
            for ($y = 0; $y < $height; $y++) {
                $color = imagecolorat($image, $x, $y);
                $alpha = ($color >> 24) & 0xFF;
                $newAlpha = (int)(127 * (100 - $opacity) / 100);
                $newColor = ($color & 0xFFFFFF) | ($newAlpha << 24);
                imagesetpixel($image, $x, $y, $newColor);
            }
        }
    }
    
    /**
     * Convert hex color to RGB
     */
    private function hexToRgb(string $hex): array
    {
        $hex = ltrim($hex, '#');
        if (strlen($hex) == 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        
        return [
            'r' => hexdec(substr($hex, 0, 2)),
            'g' => hexdec(substr($hex, 2, 2)),
            'b' => hexdec(substr($hex, 4, 2))
        ];
    }
    
    /**
     * Save image to file
     */
    public function saveImage(\GdImage $image, string $outputPath, array $watermarkData = []): bool
    {
        $extension = strtolower(pathinfo($outputPath, PATHINFO_EXTENSION));
        $quality = $watermarkData['watermark_quality'] ?? 90;
        $imageFormat = $watermarkData['image_format'] ?? 'baseline';
        
        
        switch ($extension) {
            case 'jpg':
            case 'jpeg':
                // Set progressive JPEG if requested
                if ($imageFormat === 'progressive') {
                    imageinterlace($image, 1);
                } else {
                    imageinterlace($image, 0);
                }
                return imagejpeg($image, $outputPath, $quality);
                
            case 'png':
                // PNG compression level (0-9, where 9 is maximum compression)
                $compression = (int) ((100 - $quality) / 10);
                $compression = max(0, min(9, $compression));
                return imagepng($image, $outputPath, $compression);
                
            case 'gif':
                return imagegif($image, $outputPath);
                
            case 'webp':
                return imagewebp($image, $outputPath, $quality);
                
            default:
                return false;
        }
    }
    
    /**
     * Check if processor is available
     */
    public function isAvailable(): bool
    {
        return extension_loaded('gd') && function_exists('imagecreatefromjpeg');
    }
}