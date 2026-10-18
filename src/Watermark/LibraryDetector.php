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
     * Whether the installed ImageMagick can read AND write a file format.
     * Some hosts ship Imagick without JPEG/WebP delegates.
     *
     * @param string $extension File extension (jpg, png, webp, …).
     */
    public static function imagickSupportsFormat(string $extension): bool
    {
        static $cache = [];

        if (!self::hasImagick()) {
            return false;
        }

        $map = ['jpg' => 'JPEG', 'jpeg' => 'JPEG', 'jpe' => 'JPEG', 'png' => 'PNG', 'gif' => 'GIF', 'webp' => 'WEBP', 'avif' => 'AVIF'];
        $format = $map[strtolower($extension)] ?? strtoupper($extension);

        if (!isset($cache[$format])) {
            try {
                $cache[$format] = !empty(\Imagick::queryFormats($format));
            } catch (\Throwable $e) {
                $cache[$format] = false;
            }
        }

        return $cache[$format];
    }

    /**
     * Whether GD can read and write a file format.
     */
    public static function gdSupportsFormat(string $extension): bool
    {
        if (!self::hasGD()) {
            return false;
        }

        switch (strtolower($extension)) {
            case 'jpg':
            case 'jpeg':
            case 'jpe':
                return function_exists('imagecreatefromjpeg') && function_exists('imagejpeg');
            case 'png':
                return function_exists('imagecreatefrompng') && function_exists('imagepng');
            case 'gif':
                return function_exists('imagecreatefromgif') && function_exists('imagegif');
            case 'webp':
                return function_exists('imagecreatefromwebp') && function_exists('imagewebp');
            default:
                return false;
        }
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
