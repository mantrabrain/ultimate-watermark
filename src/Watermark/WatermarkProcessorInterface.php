<?php

namespace MantraBrain\UltimateWatermark\Watermark;

/**
 * Watermark Processor Interface
 * 
 * Defines the contract for watermark processing implementations
 */
interface WatermarkProcessorInterface
{
    /**
     * Apply watermark to image
     * 
     * @param string $sourceImagePath Path to source image
     * @param string $outputImagePath Path to save watermarked image
     * @param array $watermarkData Watermark configuration
     * @return bool Success status
     */
    public function applyWatermark(string $sourceImagePath, string $outputImagePath, array $watermarkData): bool;

    /**
     * Generate preview image
     * 
     * @param string $sourceImagePath Path to source image
     * @param array $watermarkData Watermark configuration
     * @return string|false Preview image path or false on failure
     */
    public function generatePreview(string $sourceImagePath, array $watermarkData);

    /**
     * Get supported image formats
     * 
     * @return array Array of supported formats
     */
    public function getSupportedFormats(): array;

    /**
     * Check if processor is available
     * 
     * @return bool
     */
    public function isAvailable(): bool;
}
