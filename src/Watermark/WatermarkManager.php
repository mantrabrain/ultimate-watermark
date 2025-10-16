<?php

namespace MantraBrain\UltimateWatermark\Watermark;

/**
 * Dynamic Watermark Manager
 * 
 * Legacy wrapper for backward compatibility
 * 
 * @deprecated Use WatermarkService instead for all new implementations
 */
class WatermarkManager
{
    /**
     * Get the watermark processor instance
     * 
     * @deprecated Use WatermarkProcessorFactory::create() instead
     */
    public static function getProcessor(): WatermarkProcessorInterface
    {
        return WatermarkProcessorFactory::create();
    }

    /**
     * Apply watermark to image (unified method)
     * 
     * @deprecated Use WatermarkService::applyWatermark() instead
     */
    public static function applyWatermark(string $sourceImagePath, string $outputImagePath, array $watermarkData): bool
    {
        try {
            return WatermarkService::applyWatermark($sourceImagePath, $watermarkData, $outputImagePath);
        } catch (\Exception $e) {
            error_log('Watermark error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Apply watermark to image using watermark ID (unified method)
     * 
     * @deprecated Use WatermarkService::applyWatermarkById() instead
     */
    public static function applyWatermarkById(string $sourceImagePath, string $outputImagePath, int $watermarkId): bool
    {
        try {
            return WatermarkService::applyWatermarkById($sourceImagePath, $watermarkId, $outputImagePath);
        } catch (\Exception $e) {
            error_log('Watermark error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Generate preview image (unified method)
     * 
     * @deprecated Use WatermarkService::generatePreview() instead
     */
    public static function generatePreview(string $sourceImagePath, array $watermarkData): string|false
    {
        try {
            return WatermarkService::generatePreview($sourceImagePath, $watermarkData);
        } catch (\Exception $e) {
            error_log('Preview generation error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get current library being used
     * 
     * @deprecated Use WatermarkService::getCurrentLibrary() instead
     */
    public static function getCurrentLibrary(): ?string
    {
        return WatermarkService::getCurrentLibrary();
    }

    /**
     * Check if watermarking is available
     * 
     * @deprecated Use WatermarkService::isAvailable() instead
     */
    public static function isAvailable(): bool
    {
        return WatermarkService::isAvailable();
    }

    /**
     * Get library status for admin
     * 
     * @deprecated Use WatermarkService::getLibraryStatus() instead
     */
    public static function getLibraryStatus(): array
    {
        return WatermarkService::getLibraryStatus();
    }
}
