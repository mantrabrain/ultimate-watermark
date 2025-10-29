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
        
        $watermarkType = $watermarkData['watermark_type'] ?? 'text';
        
        if ($watermarkType === 'text') {
            $this->applyTextWatermark($image, $watermarkData);
        } elseif ($watermarkType === 'image') {
            $this->applyImageWatermark($image, $watermarkData);
        } else {
        }
        
        // Save watermarked image with quality and format settings
        $result = $this->saveImage($image, $outputImagePath, $watermarkData);
        $image->destroy();
        
        return $result;
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
                if (file_exists($file)) {
                    unlink($file);
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
        
        
        // Debug: List available fonts (only once per request)
        static $fontsListed = false;
        if (!$fontsListed) {
            $this->debugAvailableFonts();
            $fontsListed = true;
        }
        
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
        
        
        // Check if watermark image has any non-transparent pixels
        $watermarkImage->setImageAlphaChannel(\Imagick::ALPHACHANNEL_DEACTIVATE);
        $watermarkImage->setImageBackgroundColor('white');
        $watermarkImage->setImageAlphaChannel(\Imagick::ALPHACHANNEL_DEACTIVATE);
        
        // Get image statistics to check if it has content
        $stats = $watermarkImage->getImageChannelStatistics();
        
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
     * Debug method to list available fonts
     */
    private function debugAvailableFonts(): void
    {
        try {
            $imagick = new \Imagick();
            $fonts = $imagick->queryFonts();
        } catch (Exception $e) {
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