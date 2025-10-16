<?php

namespace MantraBrain\UltimateWatermark\Watermark;

/**
 * Library Detection System
 * 
 * Detects available image processing libraries (GD, Imagick)
 * and provides unified interface for watermark operations
 */
class LibraryDetector
{
    /**
     * Check if GD extension is available
     */
    public static function hasGD(): bool
    {
        return extension_loaded('gd') && function_exists('imagecreatefromjpeg');
    }

    /**
     * Check if Imagick extension is available
     */
    public static function hasImagick(): bool
    {
        return extension_loaded('imagick') && class_exists('Imagick');
    }

    /**
     * Get available libraries
     */
    public static function getAvailableLibraries(): array
    {
        $libraries = [];
        
        if (self::hasGD()) {
            $libraries[] = 'gd';
        }
        
        if (self::hasImagick()) {
            $libraries[] = 'imagick';
        }
        
        return $libraries;
    }

    /**
     * Get preferred library (Imagick first, then GD)
     */
    public static function getPreferredLibrary(): ?string
    {
        if (self::hasImagick()) {
            return 'imagick';
        }
        
        if (self::hasGD()) {
            return 'gd';
        }
        
        return null;
    }

    /**
     * Check if any library is available
     */
    public static function hasAnyLibrary(): bool
    {
        return self::hasGD() || self::hasImagick();
    }

    /**
     * Get library status for admin notifications
     */
    public static function getLibraryStatus(): array
    {
        return [
            'gd' => [
                'available' => self::hasGD(),
                'name' => 'GD Library',
                'description' => 'PHP GD extension for image processing'
            ],
            'imagick' => [
                'available' => self::hasImagick(),
                'name' => 'ImageMagick',
                'description' => 'ImageMagick PHP extension for advanced image processing'
            ]
        ];
    }
}
