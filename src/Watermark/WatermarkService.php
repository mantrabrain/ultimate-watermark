<?php

namespace MantraBrain\UltimateWatermark\Watermark;

use MantraBrain\UltimateWatermark\Utils\WatermarkHelper;

/**
 * Watermark Service - Single Entry Point
 * 
 * Provides a unified interface for all watermark operations
 * Handles both preview generation and real watermark application
 * 
 * @package UltimateWatermark
 * @since 2.0.0
 */
class WatermarkService
{
    /**
     * Apply watermark to image
     * 
     * @param string $sourceImagePath Path to source image
     * @param int|array $watermarkData Watermark ID (int) or form data (array)
     * @param string|null $outputImagePath Output path (optional, defaults to source)
     * @return bool|string Success status or preview URL
     * @throws \Exception
     */
    public static function applyWatermark(string $sourceImagePath, $watermarkData, ?string $outputImagePath = null)
    {
        // Validate source image
        if (!file_exists($sourceImagePath)) {
            throw new \Exception('Source image not found: ' . $sourceImagePath);
        }

        // Resolve watermark data
        $resolvedData = WatermarkDataResolver::resolve($watermarkData);
        if (!$resolvedData) {
            throw new \Exception('Invalid watermark data provided');
        }
        
        // Store source image path for scaling calculations
        $resolvedData['_source_image_path'] = $sourceImagePath;

        // Get processor
        $processor = WatermarkProcessorFactory::create();
        
        // Determine output path
        if ($outputImagePath === null) {
            $outputImagePath = $sourceImagePath; // Apply to source
        }

        // Apply watermark
        return $processor->applyWatermark($sourceImagePath, $outputImagePath, $resolvedData);
    }

    /**
     * Generate preview image
     * 
     * @param string $sourceImagePath Path to source image
     * @param int|array $watermarkData Watermark ID (int) or form data (array)
     * @return string|false Preview URL or false on failure
     * @throws \Exception
     */
    public static function generatePreview(string $sourceImagePath, $watermarkData)
    {
        // Validate source image
        if (!file_exists($sourceImagePath)) {
            throw new \Exception('Source image not found: ' . $sourceImagePath);
        }

        // Resolve watermark data
        $resolvedData = WatermarkDataResolver::resolve($watermarkData);
        if (!$resolvedData) {
            throw new \Exception('Invalid watermark data provided');
        }

        // Get processor
        $processor = WatermarkProcessorFactory::create();
        
        // Generate preview
        return $processor->generatePreview($sourceImagePath, $resolvedData);
    }

    /**
     * Apply watermark by ID (convenience method)
     * 
     * @param string $sourceImagePath Path to source image
     * @param int $watermarkId Watermark ID
     * @param string|null $outputImagePath Output path (optional)
     * @return bool Success status
     */
    public static function applyWatermarkById(string $sourceImagePath, int $watermarkId, ?string $outputImagePath = null): bool
    {
        try {
            return (bool) self::applyWatermark($sourceImagePath, $watermarkId, $outputImagePath);
        } catch (\Exception $e) {
            error_log('WatermarkService: Error applying watermark by ID: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Apply watermark by form data (convenience method)
     * 
     * @param string $sourceImagePath Path to source image
     * @param array $formData Form data array
     * @param string|null $outputImagePath Output path (optional)
     * @return bool Success status
     */
    public static function applyWatermarkByFormData(string $sourceImagePath, array $formData, ?string $outputImagePath = null): bool
    {
        try {
            return (bool) self::applyWatermark($sourceImagePath, $formData, $outputImagePath);
        } catch (\Exception $e) {
            error_log('WatermarkService: Error applying watermark by form data: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Check if watermarking is available
     * 
     * @return bool
     */
    public static function isAvailable(): bool
    {
        return WatermarkProcessorFactory::isAvailable();
    }

    /**
     * Get current library being used
     * 
     * @return string|null
     */
    public static function getCurrentLibrary(): ?string
    {
        return WatermarkProcessorFactory::getCurrentLibrary();
    }

    /**
     * Get library status information
     * 
     * @return array
     */
    public static function getLibraryStatus(): array
    {
        return WatermarkProcessorFactory::getLibraryStatus();
    }
}
