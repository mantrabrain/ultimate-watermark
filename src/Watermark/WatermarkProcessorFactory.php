<?php

namespace MantraBrain\UltimateWatermark\Watermark;

/**
 * Watermark Processor Factory
 * 
 * Handles library detection and processor creation
 * Provides unified interface for processor management
 * 
 * @package UltimateWatermark
 * @since 2.0.0
 */
class WatermarkProcessorFactory
{
    private static ?WatermarkProcessorInterface $processor = null;
    private static ?string $currentLibrary = null;

    /**
     * Create watermark processor instance
     * 
     * @return WatermarkProcessorInterface
     * @throws \Exception
     */
    public static function create(): WatermarkProcessorInterface
    {
        if (self::$processor === null) {
            self::$processor = self::createProcessor();
        }
        
        return self::$processor;
    }

    /**
     * Create the appropriate processor based on available libraries
     * 
     * @return WatermarkProcessorInterface
     * @throws \Exception
     */
    private static function createProcessor(): WatermarkProcessorInterface
    {
        $preferredLibrary = LibraryDetector::getPreferredLibrary();
        
        if ($preferredLibrary === 'imagick' && LibraryDetector::hasImagick()) {
            self::$currentLibrary = 'imagick';
            return new Processors\ImagickWatermarkProcessor();
        }
        
        if (LibraryDetector::hasGD()) {
            self::$currentLibrary = 'gd';
            return new Processors\GDWatermarkProcessor();
        }
        
        throw new \Exception('No image processing library available. Please install GD or Imagick extension.');
    }

    /**
     * Get current library being used
     * 
     * @return string|null
     */
    public static function getCurrentLibrary(): ?string
    {
        if (self::$currentLibrary === null) {
            try {
                self::create(); // Initialize if not already done
            } catch (\Exception $e) {
                return null;
            }
        }
        
        return self::$currentLibrary;
    }

    /**
     * Check if watermarking is available
     * 
     * @return bool
     */
    public static function isAvailable(): bool
    {
        return LibraryDetector::hasAnyLibrary();
    }

    /**
     * Get library status information
     * 
     * @return array
     */
    public static function getLibraryStatus(): array
    {
        return [
            'imagick' => [
                'available' => LibraryDetector::hasImagick(),
                'name' => 'ImageMagick',
                'description' => 'ImageMagick PHP extension for advanced image processing'
            ],
            'gd' => [
                'available' => LibraryDetector::hasGD(),
                'name' => 'GD Library',
                'description' => 'PHP GD extension for image processing'
            ],
            'current' => self::getCurrentLibrary(),
            'is_available' => self::isAvailable()
        ];
    }

    /**
     * Get supported image formats
     * 
     * @return array
     */
    public static function getSupportedFormats(): array
    {
        // Basic format support - can be enhanced later
        $commonFormats = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        
        if (self::getCurrentLibrary() === 'imagick') {
            // Imagick supports more formats
            return array_merge($commonFormats, ['tiff', 'bmp', 'svg']);
        } elseif (self::getCurrentLibrary() === 'gd') {
            // GD supports basic formats
            return $commonFormats;
        }
        
        return $commonFormats;
    }

    /**
     * Reset processor instance (for testing or library changes)
     */
    public static function reset(): void
    {
        self::$processor = null;
        self::$currentLibrary = null;
    }

    /**
     * Force specific library (for testing)
     * 
     * @param string $library Library name ('imagick' or 'gd')
     * @throws \Exception
     */
    public static function forceLibrary(string $library): void
    {
        self::reset();
        
        if ($library === 'imagick' && LibraryDetector::hasImagick()) {
            self::$currentLibrary = 'imagick';
            self::$processor = new Processors\ImagickWatermarkProcessor();
        } elseif ($library === 'gd' && LibraryDetector::hasGD()) {
            self::$currentLibrary = 'gd';
            self::$processor = new Processors\GDWatermarkProcessor();
        } else {
            throw new \Exception('Requested library not available: ' . $library);
        }
    }
}
