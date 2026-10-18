<?php

namespace MantraBrain\UltimateWatermark\Watermark\Processors;

use MantraBrain\UltimateWatermark\Watermark\WatermarkProcessorInterface;

/**
 * GD Watermark Processor Class
 *
 * Applies watermarks with the PHP GD library. Output matches the Imagick
 * processor: same positioning, sizing ("scaled" = % of the target image
 * width), rotation, opacity, percentage offsets and tiled layout.
 *
 * @package UltimateWatermark
 * @since 2.0.0
 */
class GDWatermarkProcessor implements WatermarkProcessorInterface
{
    /**
     * Maximum image dimensions allowed
     */
    private const MAX_IMAGE_WIDTH = 15000;
    private const MAX_IMAGE_HEIGHT = 15000;

    /**
     * Maximum file size for image processing (50MB)
     */
    private const MAX_FILE_SIZE = 52428800; // 50 * 1024 * 1024

    /**
     * Default quality settings
     */
    private const DEFAULT_QUALITY = 90;
    private const MIN_QUALITY = 1;
    private const MAX_QUALITY = 100;

    /**
     * Default font size limits
     */
    private const MIN_FONT_SIZE = 8;

    /**
     * Upper bound on tiles drawn on one image (protects against tiny tiles on huge images).
     */
    private const MAX_TILES = 5000;

    /**
     * Supported image formats
     */
    private const SUPPORTED_FORMATS = [
        IMAGETYPE_JPEG => 'jpeg',
        IMAGETYPE_PNG => 'png',
        IMAGETYPE_GIF => 'gif',
        IMAGETYPE_WEBP => 'webp'
    ];

    /**
     * Resolved font paths for this request, keyed by family|weight|style.
     *
     * @var array<string, string>
     */
    private static array $fontCache = [];

    /**
     * Apply watermark to image
     */
    public function applyWatermark(string $sourceImagePath, string $outputImagePath, array $watermarkData): bool
    {
        try {
            $this->validateInputs($sourceImagePath, $outputImagePath, $watermarkData);
            $this->prepareMemoryLimits();

            $image = $this->loadImage($sourceImagePath);
            if (!$image) {
                throw new \RuntimeException('Failed to load source image: ' . basename($sourceImagePath));
            }

            try {
                $scaledWatermarkData = $this->scaleWatermarkDataForImage($image, $watermarkData);
                $watermarkType = $scaledWatermarkData['watermark_type'] ?? 'text';
                $this->applyWatermarkByType($image, $scaledWatermarkData, $watermarkType);

                return $this->saveImage($image, $outputImagePath, $watermarkData);
            } finally {
                $this->destroy($image);
            }
        } catch (\Throwable $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Ultimate Watermark GD Processor Error: ' . $e->getMessage());
            }
            return false;
        }
    }

    /**
     * Generate preview image
     */
    /**
     * @return string|false Preview URL or false on failure.
     */
    public function generatePreview(string $sourceImagePath, array $watermarkData)
    {
        try {
            $this->validateInputs($sourceImagePath, '', $watermarkData);
            $this->prepareMemoryLimits();

            $image = $this->loadImage($sourceImagePath);
            if (!$image) {
                throw new \RuntimeException('Failed to load source image for preview');
            }

            try {
                // The preview image is its own "full size" reference.
                $previewData = $watermarkData;
                $previewData['_full_size_width']  = imagesx($image);
                $previewData['_full_size_height'] = imagesy($image);

                $scaledWatermarkData = $this->scaleWatermarkDataForImage($image, $previewData);
                $watermarkType = $scaledWatermarkData['watermark_type'] ?? 'text';
                $this->applyWatermarkByType($image, $scaledWatermarkData, $watermarkType);

                return $this->generatePreviewUrl($image, $watermarkData);
            } finally {
                $this->destroy($image);
            }
        } catch (\Throwable $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Ultimate Watermark GD Preview Error: ' . $e->getMessage());
            }
            return false;
        }
    }

    /**
     * Validate input parameters
     */
    private function validateInputs(string $sourcePath, string $outputPath, array $watermarkData): void
    {
        if (!file_exists($sourcePath) || !is_readable($sourcePath)) {
            throw new \InvalidArgumentException('Source image file not found or not readable: ' . basename($sourcePath));
        }

        if (!empty($outputPath)) {
            $outputDir = dirname($outputPath);
            if (!is_dir($outputDir) || !is_writable($outputDir)) {
                throw new \InvalidArgumentException('Output directory is not writable: ' . $outputDir);
            }
        }

        if (empty($watermarkData)) {
            throw new \InvalidArgumentException('Watermark data cannot be empty');
        }

        $imageInfo = @getimagesize($sourcePath);
        $imageType = $imageInfo ? (int) $imageInfo[2] : 0;
        if (!isset(self::SUPPORTED_FORMATS[$imageType])) {
            throw new \InvalidArgumentException('Unsupported image format: ' . $imageType);
        }
    }

    /**
     * Raise (never lower) the memory limit and check GD support.
     */
    private function prepareMemoryLimits(): void
    {
        if (function_exists('wp_raise_memory_limit')) {
            wp_raise_memory_limit('image');
        }

        if (!extension_loaded('gd') || !function_exists('gd_info')) {
            throw new \RuntimeException('GD extension is not available');
        }

        $gdInfo = gd_info();
        if (empty($gdInfo['PNG Support']) && empty($gdInfo['JPEG Support'])) {
            throw new \RuntimeException('GD library lacks required image format support');
        }
    }

    /**
     * Load image as a true-colour resource with alpha preserved.
     */
    /**
     * @return \GdImage|resource|false GD image (a resource on PHP 7.4).
     */
    private function loadImage(string $imagePath)
    {
        if (!file_exists($imagePath)) {
            return false;
        }

        if (filesize($imagePath) > self::MAX_FILE_SIZE) {
            throw new \RuntimeException('Image file too large: ' . basename($imagePath));
        }

        $imageInfo = getimagesize($imagePath);
        if (!$imageInfo) {
            throw new \RuntimeException('Unable to get image information: ' . basename($imagePath));
        }

        [$width, $height, $type] = $imageInfo;

        if ($width > self::MAX_IMAGE_WIDTH || $height > self::MAX_IMAGE_HEIGHT) {
            throw new \RuntimeException('Image dimensions too large: ' . $width . 'x' . $height);
        }

        // Running out of memory is a fatal error that cannot be caught, so refuse
        // images that will not fit (truecolor pixels take 4 bytes, plus decoder
        // and watermark-layer overhead).
        $memoryLimit = function_exists('wp_convert_hr_to_bytes')
            ? wp_convert_hr_to_bytes((string) ini_get('memory_limit'))
            : (int) ini_get('memory_limit');
        if ($memoryLimit > 0) {
            $needed = (int) ($width * $height * 4 * 1.5);
            if (memory_get_usage(true) + $needed > $memoryLimit) {
                throw new \RuntimeException(sprintf('Not enough memory to process %dx%d image with GD', $width, $height));
            }
        }

        switch ($type) {
            case IMAGETYPE_JPEG:
                $image = imagecreatefromjpeg($imagePath);
                break;
            case IMAGETYPE_PNG:
                $image = imagecreatefrompng($imagePath);
                break;
            case IMAGETYPE_GIF:
                $image = imagecreatefromgif($imagePath);
                break;
            case IMAGETYPE_WEBP:
                if (!function_exists('imagecreatefromwebp')) {
                    throw new \RuntimeException('WebP format not supported by this GD version');
                }
                $image = imagecreatefromwebp($imagePath);
                break;
            default:
                throw new \RuntimeException('Unsupported image type: ' . $type);
        }

        if (!$image) {
            throw new \RuntimeException('Failed to create image resource from: ' . basename($imagePath));
        }

        // Palette images (GIF, 8-bit PNG) can't hold blended/alpha colours.
        if (!imageistruecolor($image)) {
            imagepalettetotruecolor($image);
        }

        // Keep alpha in the output; composite with blending on.
        imagesavealpha($image, true);
        imagealphablending($image, true);

        return $image;
    }

    /**
     * Apply watermark based on type
     */
    private function applyWatermarkByType($image, array $watermarkData, string $type): void
    {
        // Allow Pro plugin to handle custom watermark types
        $handled = apply_filters('ultimate_watermark_handle_custom_type', false, $image, $watermarkData, $type);

        if ($handled) {
            return;
        }

        switch ($type) {
            case 'text':
                $this->applyTextWatermark($image, $watermarkData);
                break;
            case 'image':
                $this->applyImageWatermark($image, $watermarkData);
                break;
            default:
                // Allow Pro plugin to register custom types
                do_action('ultimate_watermark_apply_custom_type', $image, $watermarkData, $type);

                if (!did_action('ultimate_watermark_custom_type_applied')) {
                    throw new \InvalidArgumentException('Unsupported watermark type: ' . $type);
                }
        }
    }

    /**
     * Scale font size, pixel offsets and custom dimensions from the full-size
     * image down to the image being processed (thumbnails keep proportions).
     */
    private function scaleWatermarkDataForImage($image, array $watermarkData): array
    {
        $currentWidth = imagesx($image);
        $currentHeight = imagesy($image);

        $fullDimensions = $this->getFullSizeDimensions($watermarkData, $currentWidth, $currentHeight);

        $scaleRatio = min(
            $currentWidth / max(1, $fullDimensions['width']),
            $currentHeight / max(1, $fullDimensions['height'])
        );
        $scaleRatio = max(0.05, min(1.0, $scaleRatio));

        $scaled = $watermarkData;

        if (isset($scaled['watermark_font_size'])) {
            $scaled['watermark_font_size'] = max(self::MIN_FONT_SIZE, (int) round($scaled['watermark_font_size'] * $scaleRatio));
        }

        // Percentage offsets are relative to the current image; only pixel offsets scale.
        if (!$this->usesPercentageOffsets($scaled)) {
            if (isset($scaled['watermark_offset_x'])) {
                $scaled['watermark_offset_x'] = (int) round($scaled['watermark_offset_x'] * $scaleRatio);
            }
            if (isset($scaled['watermark_offset_y'])) {
                $scaled['watermark_offset_y'] = (int) round($scaled['watermark_offset_y'] * $scaleRatio);
            }
        }

        if (isset($scaled['watermark_custom_width'])) {
            $scaled['watermark_custom_width'] = max(1, (int) round($scaled['watermark_custom_width'] * $scaleRatio));
        }
        if (isset($scaled['watermark_custom_height'])) {
            $scaled['watermark_custom_height'] = max(1, (int) round($scaled['watermark_custom_height'] * $scaleRatio));
        }

        return $scaled;
    }

    /**
     * Get full size dimensions of the attachment being processed.
     */
    private function getFullSizeDimensions(array $watermarkData, int $currentWidth, int $currentHeight): array
    {
        $width = (int) ($watermarkData['_full_size_width'] ?? 0);
        $height = (int) ($watermarkData['_full_size_height'] ?? 0);

        if ($width > 0 && $height > 0) {
            return ['width' => $width, 'height' => $height];
        }

        $attachmentId = (int) ($watermarkData['attachment_id'] ?? 0);
        if ($attachmentId <= 0 && !empty($watermarkData['_source_image_path'])) {
            $attachmentId = (int) $this->getAttachmentIdFromPath((string) $watermarkData['_source_image_path']);
        }

        if ($attachmentId > 0) {
            $metadata = wp_get_attachment_metadata($attachmentId);
            if (!empty($metadata['width']) && !empty($metadata['height'])) {
                return ['width' => (int) $metadata['width'], 'height' => (int) $metadata['height']];
            }
        }

        return ['width' => $currentWidth, 'height' => $currentHeight];
    }

    /**
     * Apply text watermark (rendered on its own layer so rotation, opacity and
     * tiling behave exactly like image watermarks).
     */
    private function applyTextWatermark($image, array $watermarkData): void
    {
        $text = (string) ($watermarkData['watermark_text'] ?? '');
        if ($text === '') {
            throw new \InvalidArgumentException('Watermark text cannot be empty');
        }

        $fontPath = $this->getFontPath($watermarkData);
        if ($fontPath === '') {
            throw new \RuntimeException('No readable TTF font available for text rendering');
        }

        $fontSize = max(self::MIN_FONT_SIZE, (int) ($watermarkData['watermark_font_size'] ?? 20));
        $rgb = $this->hexToRgb((string) ($watermarkData['watermark_color'] ?? '#000000'));
        $opacity = max(0, min(100, (int) ($watermarkData['watermark_opacity'] ?? 50)));

        // Font sizes are pixels (as in the editor and Imagick); GD's FreeType
        // renderer works in points at 96 dpi, so convert: 1px = 0.75pt.
        $pointSize = $fontSize * 0.75;

        $bbox = imagettfbbox($pointSize, 0, $fontPath, $text);
        if ($bbox === false) {
            throw new \RuntimeException('Failed to measure text watermark');
        }

        $minX = min($bbox[0], $bbox[6]);
        $maxX = max($bbox[2], $bbox[4]);
        $minY = min($bbox[5], $bbox[7]);
        $maxY = max($bbox[1], $bbox[3]);
        $padding = (int) ceil($fontSize * 0.2);

        // Bold / italic requested but the font file has no such variant (the
        // bundled fonts are regular only): synthesise the style.
        $synthetic = $this->syntheticStyle($watermarkData, $fontPath);
        $stroke = $synthetic['bold'] ? max(1, (int) round($fontSize * 0.035)) : 0;
        $slant = $synthetic['italic'] ? 0.21 : 0.0; // tan(12°)
        $synthesise = $stroke > 0 || $slant > 0;

        $textHeight = $maxY - $minY;
        $slantWidth = (int) ceil($textHeight * $slant);
        $layerWidth = max(1, $maxX - $minX + $padding * 2 + $stroke + $slantWidth);
        $layerHeight = max(1, $textHeight + $padding * 2 + $stroke);

        $layer = $this->createTransparentCanvas($layerWidth, $layerHeight);

        try {
            imagealphablending($layer, true);
            // With synthesised styles the strokes overlap, so draw opaque and
            // apply the opacity to the finished layer (uniform transparency).
            $color = imagecolorallocatealpha($layer, $rgb['red'], $rgb['green'], $rgb['blue'], $synthesise ? 0 : $this->calculateAlpha($opacity));
            $result = false;
            for ($dx = 0; $dx <= $stroke; $dx++) {
                for ($dy = 0; $dy <= $stroke; $dy++) {
                    $result = imagettftext($layer, $pointSize, 0, $padding - $minX + $dx, $padding - $minY + $dy, $color, $fontPath, $text);
                }
            }
            if ($result === false) {
                throw new \RuntimeException('Failed to apply text watermark');
            }

            $this->applyTextDecoration($layer, $watermarkData, $padding, $padding - $minY, $maxX - $minX + $stroke, $fontSize, $color);

            if ($slant > 0) {
                $layer = $this->shearLayer($layer, $slant);
            }
            if ($synthesise && $opacity < 100) {
                $this->applyImageTransparency($layer, $opacity);
            }

            // Tiles are spaced by the unrotated size (a rotated layer's box is mostly empty).
            $baseWidth = imagesx($layer);
            $baseHeight = imagesy($layer);
            $layer = $this->rotateLayer($layer, (int) ($watermarkData['watermark_rotation'] ?? 0));
            $this->placeLayer($image, $layer, $watermarkData, $baseWidth, $baseHeight);
        } finally {
            $this->destroy($layer);
        }
    }

    /**
     * Which requested styles the font file cannot provide itself.
     *
     * @return array{bold: bool, italic: bool}
     */
    private function syntheticStyle(array $watermarkData, string $fontPath): array
    {
        $weight = strtolower((string) ($watermarkData['watermark_font_weight'] ?? 'normal'));
        $style = strtolower((string) ($watermarkData['watermark_font_style'] ?? 'normal'));
        $file = strtolower(basename($fontPath));

        return [
            'bold'   => in_array($weight, ['bold', 'bolder', '600', '700', '800', '900'], true) && !preg_match('/bold|black|heavy/', $file),
            'italic' => in_array($style, ['italic', 'oblique'], true) && !preg_match('/italic|oblique/', $file),
        ];
    }

    /**
     * Slant a layer to the right (synthetic italic): each row moves by
     * $slant x its distance from the bottom.
     */
    private function shearLayer($layer, float $slant)
    {
        $width = imagesx($layer);
        $height = imagesy($layer);
        $sheared = $this->createTransparentCanvas($width, $height);
        imagealphablending($sheared, false);

        for ($y = 0; $y < $height; $y++) {
            $shift = (int) round($slant * ($height - 1 - $y));
            if ($shift >= $width) {
                continue;
            }
            imagecopy($sheared, $layer, $shift, $y, 0, $y, $width - $shift, 1);
        }

        $this->destroy($layer);

        return $sheared;
    }

    /**
     * Draw underline / overline / line-through below, above or across the text.
     */
    private function applyTextDecoration($layer, array $watermarkData, int $x, int $baseline, int $width, int $fontSize, int $color): void
    {
        $decoration = $watermarkData['watermark_text_decoration'] ?? 'none';
        $thickness = max(1, (int) round($fontSize / 14));

        switch ($decoration) {
            case 'underline':
                $y = $baseline + $thickness * 2;
                break;
            case 'overline':
                $y = $baseline - $fontSize - $thickness;
                break;
            case 'line-through':
                $y = $baseline - (int) round($fontSize * 0.35);
                break;
            default:
                return;
        }

        imagefilledrectangle($layer, $x, max(0, $y), $x + $width, max(0, $y) + $thickness - 1, $color);
    }

    /**
     * Apply image watermark
     */
    private function applyImageWatermark($image, array $watermarkData): void
    {
        $watermarkPath = (string) ($watermarkData['watermark_image_path'] ?? '');
        if ($watermarkPath === '' || !file_exists($watermarkPath)) {
            $imageId = (int) ($watermarkData['watermark_image_id'] ?? 0);
            if (!$imageId) {
                throw new \InvalidArgumentException('Watermark image ID is required');
            }
            $watermarkPath = (string) get_attached_file($imageId);
        }

        if ($watermarkPath === '' || !file_exists($watermarkPath)) {
            throw new \RuntimeException('Watermark image file not found');
        }

        $source = $this->loadImage($watermarkPath);
        if (!$source) {
            throw new \RuntimeException('Failed to load watermark image');
        }

        $layer = null;

        try {
            $dimensions = $this->calculateWatermarkDimensions(
                imagesx($source),
                imagesy($source),
                $watermarkData,
                imagesx($image),
                imagesy($image)
            );

            // Resize onto a transparent layer first, then apply opacity to the
            // (smaller) result — far cheaper than per-pixel work on the source.
            $layer = $this->createTransparentCanvas($dimensions['width'], $dimensions['height']);
            imagecopyresampled(
                $layer,
                $source,
                0,
                0,
                0,
                0,
                $dimensions['width'],
                $dimensions['height'],
                imagesx($source),
                imagesy($source)
            );

            $opacity = max(0, min(100, (int) ($watermarkData['watermark_opacity'] ?? 100)));
            if ($opacity < 100) {
                $this->applyImageTransparency($layer, $opacity);
            }

            // Tiles are spaced by the unrotated size (a rotated layer's box is mostly empty).
            $baseWidth = imagesx($layer);
            $baseHeight = imagesy($layer);
            $layer = $this->rotateLayer($layer, (int) ($watermarkData['watermark_rotation'] ?? 0));
            $this->placeLayer($image, $layer, $watermarkData, $baseWidth, $baseHeight);
        } finally {
            $this->destroy($source);
            if ($this->isGdImage($layer)) {
                $this->destroy($layer);
            }
        }
    }

    /**
     * Composite a prepared watermark layer onto the image: once at the
     * configured position, or repeated across the whole image when tiled.
     */
    private function placeLayer($image, $layer, array $watermarkData, int $baseWidth = 0, int $baseHeight = 0): void
    {
        imagealphablending($image, true);

        $imageWidth = imagesx($image);
        $imageHeight = imagesy($image);
        $layerWidth = imagesx($layer);
        $layerHeight = imagesy($layer);

        if ((string) ($watermarkData['watermark_tile'] ?? '0') === '1') {
            $count = 0;
            foreach ($this->tileOrigins($imageWidth, $imageHeight, $layerWidth, $layerHeight, $baseWidth ?: $layerWidth, $baseHeight ?: $layerHeight, $watermarkData) as [$x, $y]) {
                imagecopy($image, $layer, $x, $y, 0, 0, $layerWidth, $layerHeight);
                if (++$count >= self::MAX_TILES) {
                    break;
                }
            }
            return;
        }

        $position = $this->calculatePosition($watermarkData, $imageWidth, $imageHeight, $layerWidth, $layerHeight);

        if (!imagecopy($image, $layer, $position['x'], $position['y'], 0, 0, $layerWidth, $layerHeight)) {
            throw new \RuntimeException('Failed to apply watermark');
        }
    }

    /**
     * Top-left positions of a grid of tiles covering the whole image.
     *
     * Tiles are spaced by the unrotated watermark size plus the spacing
     * percentage, centred on the image, and each (possibly rotated) layer is
     * centred on its grid point, so rotated text forms an even pattern.
     *
     * @return array<int, array{0: int, 1: int}>
     */
    private function tileOrigins(int $imageWidth, int $imageHeight, int $layerWidth, int $layerHeight, int $baseWidth, int $baseHeight, array $watermarkData): array
    {
        $spacing = max(0, min(300, (int) ($watermarkData['watermark_tile_spacing'] ?? 50)));
        $stepX = max(4, (int) round(max(1, $baseWidth) * (1 + $spacing / 100)));
        $stepY = max(4, (int) round(max(1, $baseHeight) * (1 + $spacing / 100)));

        $centerX = $imageWidth / 2;
        $centerY = $imageHeight / 2;
        $startX = $centerX - (int) ceil(($centerX + $layerWidth / 2) / $stepX) * $stepX;
        $startY = $centerY - (int) ceil(($centerY + $layerHeight / 2) / $stepY) * $stepY;

        $origins = [];
        $row = 0;
        for ($cy = $startY; $cy - $layerHeight / 2 < $imageHeight; $cy += $stepY, $row++) {
            // Brick layout: every other row is shifted by half a step.
            $rowStartX = $startX - (($row % 2) ? $stepX / 2 : 0);
            for ($cx = $rowStartX; $cx - $layerWidth / 2 < $imageWidth; $cx += $stepX) {
                $origins[] = [(int) round($cx - $layerWidth / 2), (int) round($cy - $layerHeight / 2)];
                if (count($origins) >= self::MAX_TILES) {
                    return $origins;
                }
            }
        }

        return $origins;
    }

    /**
     * Rotate a layer clockwise by $degrees (same direction as Imagick).
     */
    private function rotateLayer($layer, int $degrees)
    {
        $degrees = $degrees % 360;
        if ($degrees === 0) {
            return $layer;
        }

        $transparent = imagecolorallocatealpha($layer, 0, 0, 0, 127);
        $rotated = imagerotate($layer, -$degrees, $transparent);
        if (!$rotated) {
            return $layer;
        }

        imagealphablending($rotated, false);
        imagesavealpha($rotated, true);
        $this->destroy($layer);

        // GD's interpolating rotation leaves the empty area at alpha 126 (almost,
        // not fully, transparent). Invisible on one copy, but overlapping tiles
        // stack it into a visible tinted box around every watermark. Anything
        // that faint carries no watermark content, so make it fully transparent.
        $clear = imagecolorallocatealpha($rotated, 0, 0, 0, 127);
        $width = imagesx($rotated);
        $height = imagesy($rotated);
        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                if (((imagecolorat($rotated, $x, $y) >> 24) & 0x7F) === 126) {
                    imagesetpixel($rotated, $x, $y, $clear);
                }
            }
        }

        return $rotated;
    }

    /**
     * Create a fully transparent true-colour canvas.
     */
    private function createTransparentCanvas(int $width, int $height)
    {
        $canvas = imagecreatetruecolor(max(1, $width), max(1, $height));
        if (!$canvas) {
            throw new \RuntimeException('Failed to allocate watermark layer');
        }

        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
        $transparent = imagecolorallocatealpha($canvas, 0, 0, 0, 127);
        imagefilledrectangle($canvas, 0, 0, imagesx($canvas) - 1, imagesy($canvas) - 1, $transparent);

        return $canvas;
    }

    /**
     * Save image with quality and format settings
     */
    private function saveImage($image, string $outputPath, array $watermarkData): bool
    {
        $quality = $watermarkData['watermark_quality'] ?? self::DEFAULT_QUALITY;
        $imageFormat = $watermarkData['image_format'] ?? 'baseline';

        $quality = max(self::MIN_QUALITY, min(self::MAX_QUALITY, (int) $quality));

        if ($imageFormat === 'progressive') {
            imageinterlace($image, true);
        }

        $outputDir = dirname($outputPath);
        if (!is_dir($outputDir)) {
            wp_mkdir_p($outputDir);
        }

        $extension = strtolower(pathinfo($outputPath, PATHINFO_EXTENSION));

        switch ($extension) {
            case 'jpeg':
            case 'jpg':
            case 'jpe':
                return imagejpeg($image, $outputPath, $quality);
            case 'png':
                imagesavealpha($image, true);
                // PNG is lossless: "quality" does not apply, use a balanced zlib level.
                return imagepng($image, $outputPath, 6);
            case 'gif':
                return $this->saveGif($image, $outputPath);
            case 'webp':
                if (!function_exists('imagewebp')) {
                    throw new \RuntimeException('WebP output not supported');
                }
                imagesavealpha($image, true);
                return imagewebp($image, $outputPath, $quality);
            case 'avif':
                if (!function_exists('imageavif')) {
                    throw new \RuntimeException('AVIF output not supported by this GD version');
                }
                imagesavealpha($image, true);
                return imageavif($image, $outputPath, $quality);
            default:
                // Never write JPEG bytes into a file with another extension.
                throw new \RuntimeException('Unsupported output format: ' . $extension);
        }
    }

    /**
     * Save a GIF, keeping fully transparent pixels transparent (the image was
     * converted to truecolor on load; imagegif() would otherwise turn them black).
     */
    private function saveGif($image, string $outputPath): bool
    {
        $width = imagesx($image);
        $height = imagesy($image);
        $hasTransparency = false;

        for ($y = 0; $y < $height && !$hasTransparency; $y += max(1, (int) ($height / 200))) {
            for ($x = 0; $x < $width; $x += max(1, (int) ($width / 200))) {
                if (((imagecolorat($image, $x, $y) >> 24) & 0x7F) === 127) {
                    $hasTransparency = true;
                    break;
                }
            }
        }

        if (!$hasTransparency) {
            return imagegif($image, $outputPath);
        }

        // Quantise to 255 colours and reserve one palette slot for transparency.
        $palette = imagecreatetruecolor($width, $height);
        if (!$palette) {
            return imagegif($image, $outputPath);
        }

        try {
            imagealphablending($palette, false);
            imagecopy($palette, $image, 0, 0, 0, 0, $width, $height);
            imagetruecolortopalette($palette, false, 255);
            $transparent = imagecolorallocate($palette, 255, 0, 255);
            if ($transparent === false) {
                $transparent = imagecolorclosest($palette, 255, 0, 255);
            }
            imagecolortransparent($palette, $transparent);

            for ($y = 0; $y < $height; $y++) {
                for ($x = 0; $x < $width; $x++) {
                    if (((imagecolorat($image, $x, $y) >> 24) & 0x7F) >= 64) {
                        imagesetpixel($palette, $x, $y, $transparent);
                    }
                }
            }

            return imagegif($palette, $outputPath);
        } finally {
            $this->destroy($palette);
        }
    }

    /**
     * Generate preview URL
     */
    private function generatePreviewUrl($image, array $watermarkData): string
    {
        $uploadDir  = wp_upload_dir();
        $previewDir = trailingslashit($uploadDir['basedir']) . 'ultimate-watermark';

        if (!is_dir($previewDir)) {
            wp_mkdir_p($previewDir);
        }

        $indexFile = $previewDir . '/index.html';
        if (!file_exists($indexFile)) {
            @file_put_contents($indexFile, '<!-- Silence is golden. -->');
        }

        $fingerprint = md5(wp_json_encode($watermarkData) ?: serialize($watermarkData));
        // PNG keeps transparent areas of PNG/WebP sources (JPEG would turn them black).
        $previewPath = $previewDir . '/watermark_preview_' . $fingerprint . '.png';

        if (!$this->saveImage($image, $previewPath, $watermarkData)) {
            throw new \RuntimeException('Failed to write preview file');
        }

        $base     = wp_normalize_path($uploadDir['basedir']);
        $normPath = wp_normalize_path($previewPath);

        if (strpos($normPath, $base) === 0) {
            return $uploadDir['baseurl'] . substr($normPath, strlen($base));
        }
        return $uploadDir['baseurl'] . '/' . basename($previewPath);
    }

    /**
     * Get attachment ID from an image file path (full size or sub-size).
     */
    private function getAttachmentIdFromPath(string $imagePath): ?int
    {
        if ($imagePath === '') {
            return null;
        }

        $normalizedPath = wp_normalize_path($imagePath);
        $uploadDir = wp_upload_dir();
        $baseDir = wp_normalize_path($uploadDir['basedir']);

        if (strpos($normalizedPath, $baseDir) !== 0) {
            return null;
        }

        $relativePath = ltrim(substr($normalizedPath, strlen($baseDir)), '/');
        $pathInfo = pathinfo($relativePath);
        $directory = $pathInfo['dirname'] ?? '.';
        $extension = isset($pathInfo['extension']) ? '.' . $pathInfo['extension'] : '';
        $baseClean = preg_replace('/-\d+x\d+$/', '', $pathInfo['filename'] ?? '');
        $prefix = $directory === '.' ? '' : $directory . '/';

        // Exact file, the full size of a sub-size, and the "-scaled" attached
        // file that big uploads use (sub-sizes are named after the original).
        $candidates = array_unique(array_filter([
            $relativePath,
            $prefix . $baseClean . $extension,
            $prefix . $baseClean . '-scaled' . $extension,
        ]));

        global $wpdb;
        foreach ($candidates as $candidate) {
            $attachmentId = $wpdb->get_var($wpdb->prepare(
                "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_wp_attached_file' AND meta_value = %s LIMIT 1",
                $candidate
            ));
            if ($attachmentId) {
                return (int) $attachmentId;
            }
        }

        return null;
    }

    /**
     * Convert hex color to RGB
     */
    private function hexToRgb(string $hex): array
    {
        $hex = ltrim($hex, '#');

        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        if (!preg_match('/^[0-9a-fA-F]{6}$/', $hex)) {
            $hex = '000000';
        }

        return [
            'red' => hexdec(substr($hex, 0, 2)),
            'green' => hexdec(substr($hex, 2, 2)),
            'blue' => hexdec(substr($hex, 4, 2)),
        ];
    }

    /**
     * Convert opacity (0-100) to a GD alpha value (0 = opaque, 127 = transparent).
     */
    private function calculateAlpha(int $opacity): int
    {
        return (int) round((100 - max(0, min(100, $opacity))) * 1.27);
    }

    /**
     * Get font path for text watermarks.
     *
     * Resolution order:
     *   1. ultimate_watermark_resolve_font_path filter (Pro Google Fonts hook)
     *   2. Bundled serif/mono stand-ins for serif/mono families
     *   3. System sans fonts (better fidelity if installed)
     *   4. Bundled neutral sans default (UltimateWatermarkDefault.ttf)
     *
     * Results are cached per request (the filter may hit the network).
     */
    private function getFontPath(array $watermarkData = []): string
    {
        $family = (string) ($watermarkData['watermark_font_family'] ?? '');
        $weight = (string) ($watermarkData['watermark_font_weight'] ?? 'normal');
        $style  = (string) ($watermarkData['watermark_font_style']  ?? 'normal');

        $cacheKey = $family . '|' . $weight . '|' . $style;
        if (isset(self::$fontCache[$cacheKey])) {
            return self::$fontCache[$cacheKey];
        }

        $external = apply_filters('ultimate_watermark_resolve_font_path', null, $family, $weight, $style);
        if (is_string($external) && $external !== '' && file_exists($external) && is_readable($external)) {
            return self::$fontCache[$cacheKey] = $external;
        }

        $bundledDir   = plugin_dir_path(ULTIMATE_WATERMARK_FILE) . 'assets/fonts/';
        $bundledSans  = $bundledDir . 'UltimateWatermarkDefault.ttf';
        $bundledSerif = $bundledDir . 'UltimateWatermarkSerif.ttf';
        $bundledMono  = $bundledDir . 'UltimateWatermarkMono.ttf';

        // Serif / monospace families must not be replaced by a system sans font.
        $familyMap = [
            'Times New Roman' => [$bundledSerif],
            'Georgia'         => [$bundledSerif],
            'Courier New'     => [$bundledMono],
        ];

        $fontPaths = array_merge(
            $familyMap[$family] ?? [],
            [
                '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
                '/usr/share/fonts/truetype/liberation/LiberationSans-Regular.ttf',
                '/Library/Fonts/Arial.ttf',
                '/System/Library/Fonts/Supplemental/Arial.ttf',
                'C:\\Windows\\Fonts\\arial.ttf',
            ],
            [$bundledSans]
        );

        foreach ($fontPaths as $fontPath) {
            if (file_exists($fontPath) && is_readable($fontPath)) {
                return self::$fontCache[$cacheKey] = $fontPath;
            }
        }

        return self::$fontCache[$cacheKey] = '';
    }

    /**
     * Whether offsets are expressed as a percentage of the image size.
     */
    private function usesPercentageOffsets(array $watermarkData): bool
    {
        return in_array($watermarkData['watermark_offset_unit'] ?? 'pixels', ['percentage', '%'], true);
    }

    /**
     * Top-left coordinates for an item of the given size (same rules as Imagick).
     */
    private function calculatePosition(array $watermarkData, int $imageWidth, int $imageHeight, int $itemWidth, int $itemHeight): array
    {
        $position = $watermarkData['watermark_position'] ?? 'bottom-right';
        $offsetX = (int) ($watermarkData['watermark_offset_x'] ?? 0);
        $offsetY = (int) ($watermarkData['watermark_offset_y'] ?? 0);

        if ($this->usesPercentageOffsets($watermarkData)) {
            $offsetX = (int) round($imageWidth * $offsetX / 100);
            $offsetY = (int) round($imageHeight * $offsetY / 100);
        }

        $centerX = (int) (($imageWidth - $itemWidth) / 2);
        $centerY = (int) (($imageHeight - $itemHeight) / 2);

        switch ($position) {
            case 'top-left':
                $x = $offsetX;
                $y = $offsetY;
                break;
            case 'top-center':
                $x = $centerX;
                $y = $offsetY;
                break;
            case 'top-right':
                $x = $imageWidth - $itemWidth - $offsetX;
                $y = $offsetY;
                break;
            case 'center-left':
                $x = $offsetX;
                $y = $centerY;
                break;
            case 'center':
                $x = $centerX;
                $y = $centerY;
                break;
            case 'center-right':
                $x = $imageWidth - $itemWidth - $offsetX;
                $y = $centerY;
                break;
            case 'bottom-left':
                $x = $offsetX;
                $y = $imageHeight - $itemHeight - $offsetY;
                break;
            case 'bottom-center':
                $x = $centerX;
                $y = $imageHeight - $itemHeight - $offsetY;
                break;
            case 'bottom-right':
            default:
                $x = $imageWidth - $itemWidth - $offsetX;
                $y = $imageHeight - $itemHeight - $offsetY;
                break;
        }

        // Keep the watermark inside the canvas.
        $x = max(0, min($x, max(0, $imageWidth - $itemWidth)));
        $y = max(0, min($y, max(0, $imageHeight - $itemHeight)));

        return ['x' => (int) $x, 'y' => (int) $y];
    }

    /**
     * Calculate watermark image dimensions for the target image.
     *
     * - original: natural size, shrunk to fit the image if larger
     * - custom:   configured width × height
     * - scaled:   percentage of the target image width (keeps aspect ratio)
     *
     * The result is always clamped inside the target image.
     */
    private function calculateWatermarkDimensions(int $originalWidth, int $originalHeight, array $watermarkData, int $imageWidth, int $imageHeight): array
    {
        $originalWidth = max(1, $originalWidth);
        $originalHeight = max(1, $originalHeight);
        $sizeType = $watermarkData['watermark_size_type'] ?? 'original';

        switch ($sizeType) {
            case 'custom':
                $width = (int) ($watermarkData['watermark_custom_width'] ?? $originalWidth);
                $height = (int) ($watermarkData['watermark_custom_height'] ?? $originalHeight);
                break;

            case 'scaled':
                $percentage = max(1, min(100, (int) ($watermarkData['watermark_scale_percentage'] ?? 80)));
                $width = (int) round($imageWidth * $percentage / 100);
                $height = (int) round($originalHeight * $width / $originalWidth);
                break;

            case 'original':
            default:
                $width = $originalWidth;
                $height = $originalHeight;
                break;
        }

        if ($width > $imageWidth) {
            $height = (int) round($height * $imageWidth / max(1, $width));
            $width = $imageWidth;
        }
        if ($height > $imageHeight) {
            $width = (int) round($width * $imageHeight / max(1, $height));
            $height = $imageHeight;
        }

        return ['width' => max(1, $width), 'height' => max(1, $height)];
    }

    /**
     * Multiply a layer's alpha channel by the opacity, preserving its own transparency.
     */
    private function applyImageTransparency($image, int $opacity): void
    {
        if ($opacity >= 100) {
            return;
        }

        imagealphablending($image, false);
        imagesavealpha($image, true);

        $opacityMultiplier = max(0, $opacity) / 100;
        $width = imagesx($image);
        $height = imagesy($image);

        for ($x = 0; $x < $width; $x++) {
            for ($y = 0; $y < $height; $y++) {
                $rgba = imagecolorat($image, $x, $y);
                $alpha = ($rgba >> 24) & 0x7F;
                if ($alpha === 127) {
                    continue; // Already fully transparent.
                }

                $newAlpha = 127 - (int) round((127 - $alpha) * $opacityMultiplier);
                $newAlpha = max(0, min(127, $newAlpha));

                imagesetpixel($image, $x, $y, ($newAlpha << 24) | ($rgba & 0xFFFFFF));
            }
        }
    }

    /**
     * Whether a value is a GD image (GdImage object on PHP 8, resource on PHP 7.4).
     */
    private function isGdImage($image): bool
    {
        return (class_exists('\GdImage', false) && $image instanceof \GdImage)
            || (is_resource($image) && get_resource_type($image) === 'gd');
    }

    /**
     * Free a GD image (needed on PHP 7.4; a no-op since PHP 8 and deprecated in 8.5).
     */
    private function destroy($image): void
    {
        if (PHP_VERSION_ID < 80000 && is_resource($image)) {
            imagedestroy($image);
        }
    }

    /**
     * Get supported image formats
     *
     * @return array Array of supported formats
     */
    public function getSupportedFormats(): array
    {
        return ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    }

    /**
     * Check if processor is available
     *
     * @return bool True if GD library is available
     */
    public function isAvailable(): bool
    {
        return extension_loaded('gd') && function_exists('gd_info');
    }
}
