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
     * Runtime context for the current watermark operation (attachment_id, etc.)
     * Set before calling applyWatermark/applyWatermarkById, cleared after.
     */
    private static array $attachmentContext = [];

    /**
     * Files already stamped in this request: attachment => "watermark|path" => true.
     * Several image sizes can share one file (e.g. a square image's "medium" and
     * "woocommerce_thumbnail" are both foo-300x300.jpg); it must be stamped once.
     *
     * @var array<int, array<string, bool>>
     */
    private static array $stamped = [];

    /**
     * Forget what was stamped for an attachment (called after it is restored).
     */
    public static function forgetAttachment(int $attachmentId): void
    {
        unset(self::$stamped[$attachmentId]);
    }

    /**
     * Set attachment context for the current watermark operation.
     * Pro plugin uses this to resolve dynamic placeholders (EXIF, image title, etc.)
     *
     * @param array $context e.g. ['attachment_id' => 123]
     */
    public static function setAttachmentContext(array $context): void
    {
        self::$attachmentContext = $context;
    }

    /**
     * Get current attachment context.
     */
    public static function getAttachmentContext(): array
    {
        return self::$attachmentContext;
    }

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

        // Runtime context is passed into the resolver so it is visible to the
        // normalize filters (Pro resolves {placeholders} there).
        $context = array_merge(self::$attachmentContext, ['_source_image_path' => $sourceImagePath]);

        $resolvedData = WatermarkDataResolver::resolve($watermarkData, $context);
        if (!$resolvedData) {
            throw new \Exception('Invalid watermark data provided');
        }

        // Determine output path
        if ($outputImagePath === null) {
            $outputImagePath = $sourceImagePath; // Apply to source
        }

        // Watermarking would flatten an animation to a single frame.
        if (self::isAnimatedGif($sourceImagePath)) {
            return false;
        }

        $attachmentId = (int) (self::$attachmentContext['attachment_id'] ?? 0);
        $stampKey = '';
        if ($attachmentId > 0 && $sourceImagePath === $outputImagePath) {
            $watermarkKey = is_array($watermarkData) ? (string) ($resolvedData['id'] ?? md5(wp_json_encode($watermarkData))) : (string) $watermarkData;
            $stampKey = $watermarkKey . '|' . (realpath($outputImagePath) ?: $outputImagePath);
            if (isset(self::$stamped[$attachmentId][$stampKey])) {
                return true; // Same file, same watermark: already done in this request.
            }
        }

        // Write to a temporary file next to the target and move it into place
        // only on success, so a failed or partial write never corrupts the image.
        $tempPath = self::temporaryPathFor($outputImagePath);

        try {
            // Try Imagick first, then GD: a host's ImageMagick may lack a delegate
            // (e.g. JPEG or WebP).
            foreach (WatermarkProcessorFactory::createChainForFile($sourceImagePath) as $processor) {
                if (!$processor->applyWatermark($sourceImagePath, $tempPath, $resolvedData)) {
                    continue;
                }

                clearstatcache(true, $tempPath);
                if (!is_file($tempPath) || filesize($tempPath) === 0) {
                    continue;
                }

                if (!self::moveIntoPlace($tempPath, $outputImagePath)) {
                    return false;
                }
                if ($stampKey !== '') {
                    self::$stamped[$attachmentId][$stampKey] = true;
                }
                return true;
            }

            return false;
        } finally {
            if (file_exists($tempPath)) {
                @unlink($tempPath);
            }
        }
    }

    /**
     * Whether a file is a GIF with more than one frame.
     */
    public static function isAnimatedGif(string $path): bool
    {
        if (strtolower(pathinfo($path, PATHINFO_EXTENSION)) !== 'gif' || !is_readable($path)) {
            return false;
        }

        $handle = @fopen($path, 'rb');
        if (!$handle) {
            return false;
        }

        // Count frame starts (graphic control extension followed by an image
        // descriptor); stop at the second one.
        $frames = 0;
        $carry = '';
        while (!feof($handle) && $frames < 2) {
            $chunk = $carry . (string) fread($handle, 1024 * 100);
            $frames += preg_match_all('#\x00\x21\xF9\x04.{4}\x00(\x2C|\x21)#s', $chunk);
            $carry = substr($chunk, -20);
        }
        fclose($handle);

        return $frames > 1;
    }

    private static function temporaryPathFor(string $outputPath): string
    {
        // Keep the extension: processors pick the output format from it.
        return dirname($outputPath) . '/.ulwm-' . wp_generate_password(8, false, false) . '-' . basename($outputPath);
    }

    private static function moveIntoPlace(string $tempPath, string $outputPath): bool
    {
        $permissions = file_exists($outputPath) ? (fileperms($outputPath) & 0777) : 0;

        if (!@rename($tempPath, $outputPath)) {
            // rename() can fail across some network filesystems; fall back to copy.
            if (!@copy($tempPath, $outputPath)) {
                return false;
            }
        }

        if ($permissions) {
            @chmod($outputPath, $permissions);
        }
        clearstatcache(true, $outputPath);

        return true;
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

        // Resolve watermark data (with the preview's attachment context, if any)
        $resolvedData = WatermarkDataResolver::resolve($watermarkData, self::$attachmentContext);
        if (!$resolvedData) {
            throw new \Exception('Invalid watermark data provided');
        }

        foreach (WatermarkProcessorFactory::createChainForFile($sourceImagePath) as $processor) {
            $url = $processor->generatePreview($sourceImagePath, $resolvedData);
            if ($url) {
                return $url;
            }
        }

        return false;
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
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('WatermarkService: Error applying watermark by ID: ' . $e->getMessage());
            }
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
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('WatermarkService: Error applying watermark by form data: ' . $e->getMessage());
            }
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
