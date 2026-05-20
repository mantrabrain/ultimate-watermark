<?php

namespace MantraBrain\UltimateWatermark\Watermark\Processors;

use MantraBrain\UltimateWatermark\Watermark\WatermarkProcessorInterface;

/**
 * Imagick Watermark Processor
 *
 * Renders text and image watermarks using the ImageMagick PHP extension.
 *
 * @package UltimateWatermark
 * @since   2.0.0
 */
class ImagickWatermarkProcessor implements WatermarkProcessorInterface
{
    private const MIN_FONT_SIZE = 8;

    /**
     * Cached resolved TTF font path (per-request).
     */
    private static ?string $fallbackFontPath = null;

    /**
     * Apply watermark to image and write to $outputImagePath.
     */
    public function applyWatermark(string $sourceImagePath, string $outputImagePath, array $watermarkData): bool
    {
        try {
            $image = $this->loadImage($sourceImagePath);

            try {
                $scaled = $this->scaleWatermarkDataForImage($image, $watermarkData);
                $this->renderWatermark($image, $scaled);
                return $this->saveImage($image, $outputImagePath, $watermarkData);
            } finally {
                $image->clear();
                $image->destroy();
            }
        } catch (\Throwable $e) {
            $this->logError('applyWatermark', $e);
            return false;
        }
    }

    /**
     * Generate a preview image and return its public URL.
     *
     * @return string|false URL on success, false on failure
     */
    public function generatePreview(string $sourceImagePath, array $watermarkData)
    {
        try {
            $image = $this->loadImage($sourceImagePath);

            try {
                // Treat the preview source as the "full size" reference so that
                // user-configured offsets/font sizes render at their natural scale.
                $previewData = $watermarkData;
                $previewData['_full_size_width']  = $image->getImageWidth();
                $previewData['_full_size_height'] = $image->getImageHeight();

                $scaled = $this->scaleWatermarkDataForImage($image, $previewData);
                $this->renderWatermark($image, $scaled);

                $previewPath = $this->preparePreviewPath($watermarkData);

                if (!$this->saveImage($image, $previewPath, $watermarkData)) {
                    throw new \RuntimeException('Imagick failed to write preview file: ' . $previewPath);
                }

                return $this->pathToUrl($previewPath);
            } finally {
                $image->clear();
                $image->destroy();
            }
        } catch (\Throwable $e) {
            $this->logError('generatePreview', $e);
            return false;
        }
    }

    /**
     * Get supported image formats.
     */
    public function getSupportedFormats(): array
    {
        return ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    }

    /**
     * Whether this processor is usable on the current host.
     */
    public function isAvailable(): bool
    {
        return extension_loaded('imagick') && class_exists('\Imagick');
    }

    // ---------------------------------------------------------------------
    // Internal helpers
    // ---------------------------------------------------------------------

    private function loadImage(string $sourceImagePath): \Imagick
    {
        if (!file_exists($sourceImagePath) || !is_readable($sourceImagePath)) {
            throw new \RuntimeException('Source image not found or unreadable: ' . $sourceImagePath);
        }

        $image = new \Imagick();
        $image->readImage($sourceImagePath);

        // Strip EXIF orientation so preview/output match the displayed image.
        if (method_exists($image, 'autoOrient')) {
            $image->autoOrient();
        }

        return $image;
    }

    private function renderWatermark(\Imagick $image, array $watermarkData): void
    {
        $type = $watermarkData['watermark_type'] ?? 'text';

        // Allow Pro plugin (or other extensions) to handle custom watermark types.
        // Same filter name used by GD processor for cross-library compatibility.
        $handled = apply_filters('ultimate_watermark_handle_custom_type', false, $image, $watermarkData, $type);
        if ($handled) {
            return;
        }

        if ($type === 'text') {
            $this->applyTextWatermark($image, $watermarkData);
            return;
        }

        if ($type === 'image') {
            $this->applyImageWatermark($image, $watermarkData);
            return;
        }

        // Unknown types: let Pro hook in.
        do_action('ultimate_watermark_apply_custom_type', $image, $watermarkData, $type);
    }

    /**
     * Scale font/offset/dimension fields proportionally for the current image.
     *
     * Watermarks are configured against the original (full-size) image; when
     * applied to a thumbnail or smaller preview we scale values down so visual
     * weight stays consistent.
     */
    private function scaleWatermarkDataForImage(\Imagick $image, array $watermarkData): array
    {
        $currentWidth  = $image->getImageWidth();
        $currentHeight = $image->getImageHeight();

        $fullWidth  = $watermarkData['_full_size_width']  ?? null;
        $fullHeight = $watermarkData['_full_size_height'] ?? null;

        if (($fullWidth === null || $fullHeight === null) && !empty($watermarkData['_source_image_path'])) {
            $attachmentId = $this->getAttachmentIdFromPath($watermarkData['_source_image_path']);
            if ($attachmentId) {
                $metadata = wp_get_attachment_metadata($attachmentId);
                if (!empty($metadata['width']) && !empty($metadata['height'])) {
                    $fullWidth  = (int) $metadata['width'];
                    $fullHeight = (int) $metadata['height'];
                }
            }
        }

        if (!$fullWidth || !$fullHeight) {
            $fullWidth  = $currentWidth;
            $fullHeight = $currentHeight;
        }

        $scaleRatio = min($currentWidth / $fullWidth, $currentHeight / $fullHeight);
        $scaleRatio = max(0.05, min(1.0, $scaleRatio)); // safety clamp

        $scaled = $watermarkData;

        if (isset($scaled['watermark_font_size'])) {
            $scaled['watermark_font_size'] = max(self::MIN_FONT_SIZE, (int) round($scaled['watermark_font_size'] * $scaleRatio));
        }
        if (isset($scaled['watermark_offset_x'])) {
            $scaled['watermark_offset_x'] = (int) round($scaled['watermark_offset_x'] * $scaleRatio);
        }
        if (isset($scaled['watermark_offset_y'])) {
            $scaled['watermark_offset_y'] = (int) round($scaled['watermark_offset_y'] * $scaleRatio);
        }
        if (isset($scaled['watermark_custom_width'])) {
            $scaled['watermark_custom_width']  = max(10, (int) round($scaled['watermark_custom_width'] * $scaleRatio));
        }
        if (isset($scaled['watermark_custom_height'])) {
            $scaled['watermark_custom_height'] = max(10, (int) round($scaled['watermark_custom_height'] * $scaleRatio));
        }

        $scaled['_current_image_width']  = $currentWidth;
        $scaled['_current_image_height'] = $currentHeight;
        $scaled['_scale_ratio']          = $scaleRatio;

        return $scaled;
    }

    private function getAttachmentIdFromPath(string $imagePath): ?int
    {
        if ($imagePath === '') {
            return null;
        }

        $normalizedPath = wp_normalize_path($imagePath);
        $uploadDir      = wp_upload_dir();
        $baseDir        = wp_normalize_path($uploadDir['basedir']);

        if (strpos($normalizedPath, $baseDir) !== 0) {
            return null;
        }

        $relativePath = ltrim(str_replace($baseDir, '', $normalizedPath), '/');
        $pathInfo     = pathinfo($relativePath);
        $directory    = $pathInfo['dirname'] ?? '.';
        $extension    = isset($pathInfo['extension']) ? '.' . $pathInfo['extension'] : '';
        $baseClean    = preg_replace('/-\d+x\d+$/', '', $pathInfo['filename'] ?? '');

        global $wpdb;

        $candidates = [$relativePath];
        if ($baseClean !== '') {
            $candidates[] = ($directory === '.' ? '' : $directory . '/') . $baseClean . $extension;
        }
        $candidates = array_unique(array_filter($candidates));

        foreach ($candidates as $candidate) {
            $id = $wpdb->get_var($wpdb->prepare(
                "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_wp_attached_file' AND meta_value = %s LIMIT 1",
                $candidate
            ));
            if ($id) {
                return (int) $id;
            }
        }

        return null;
    }

    // ---------------------------------------------------------------------
    // Text watermark
    // ---------------------------------------------------------------------

    private function applyTextWatermark(\Imagick $image, array $watermarkData): void
    {
        $text = (string) ($watermarkData['watermark_text'] ?? '');
        if ($text === '') {
            return;
        }

        $fontSize = max(self::MIN_FONT_SIZE, (int) ($watermarkData['watermark_font_size'] ?? 24));
        $opacity  = max(0, min(100, (int) ($watermarkData['watermark_opacity'] ?? 50)));
        $rotation = (int) ($watermarkData['watermark_rotation'] ?? 0);

        // Resolve the font path BEFORE creating the draw object so we can
        // bail out cleanly if no usable font exists on this host. Otherwise
        // queryFontMetrics()/annotateImage() throw a cryptic FreeType error
        // ("unable to read font `'") on hosts with no system fonts.
        $imageFontPath = $this->resolvedFontPathFor($watermarkData);
        if ($imageFontPath === null || !file_exists($imageFontPath) || !is_readable($imageFontPath)) {
            throw new \RuntimeException(
                'Ultimate Watermark: no readable TTF/OTF font available for text rendering. '
                . 'The bundled fallback at assets/fonts/UltimateWatermarkDefault.ttf appears to be '
                . 'missing or unreadable — re-upload the plugin to restore it.'
            );
        }

        $draw = $this->createTextDrawObject($watermarkData, $fontSize, $opacity, $imageFontPath);

        // Mirror the resolved font onto the Imagick image as well — this
        // works around a long-standing macOS Homebrew ImageMagick bug where
        // ImagickDraw::setFont() with a path containing spaces (e.g. the
        // Local Sites/ folder on a Local-by-Flywheel install) is silently
        // dropped by the MVG parser. Setting on the image is a separate
        // code path and accepts the same path verbatim.
        try {
            $image->setFont($imageFontPath);
        } catch (\Throwable $e) {
            // Non-fatal — annotateImage will use the draw's font instead.
        }

        if ($rotation === 0) {
            $metrics    = $image->queryFontMetrics($draw, $text);
            $textWidth  = (int) $metrics['textWidth'];
            $textHeight = (int) $metrics['textHeight'];

            $position = $this->calculatePosition(
                $watermarkData,
                $image->getImageWidth(),
                $image->getImageHeight(),
                $textWidth,
                $textHeight
            );

            // annotateImage draws from the text baseline, so we offset by the ascender.
            $ascender = (int) ($metrics['ascender'] ?? $textHeight);
            $image->annotateImage($draw, $position['x'], $position['y'] + $ascender, 0, $text);
            return;
        }

        $this->drawRotatedText($image, $draw, $text, $rotation, $watermarkData);
    }

    private function createTextDrawObject(array $watermarkData, int $fontSize, int $opacity, ?string $fontPath = null): \ImagickDraw
    {
        $color = $this->hexToRgb((string) ($watermarkData['watermark_color'] ?? '#000000'));

        $draw = new \ImagickDraw();
        $draw->setFontSize($fontSize);
        $draw->setFillColor($color);
        $draw->setFillOpacity($opacity / 100);
        $draw->setTextAntialias(true);

        // Caller may pass a pre-resolved font path (the common case from
        // applyTextWatermark, which needs the same path for $image->setFont).
        // If absent, resolve from the watermark data here for callers that
        // didn't pre-resolve.
        if ($fontPath === null) {
            $fontPath = $this->resolvedFontPathFor($watermarkData);
        }

        if ($fontPath !== null) {
            try {
                $draw->setFont($fontPath);
            } catch (\Throwable $e) {
                // Non-fatal — Imagick will fall back to its default font.
            }
        }

        $this->applyTextDecoration($draw, $watermarkData);

        return $draw;
    }

    private function drawRotatedText(\Imagick $image, \ImagickDraw $draw, string $text, int $rotation, array $watermarkData): void
    {
        $metrics    = $image->queryFontMetrics($draw, $text);
        $textWidth  = (int) $metrics['textWidth'];
        $textHeight = (int) $metrics['textHeight'];
        $ascender   = (int) ($metrics['ascender'] ?? $textHeight);

        $padding = 10;

        $tempImage = new \Imagick();
        $tempImage->newImage($textWidth + $padding * 2, $textHeight + $padding * 2, new \ImagickPixel('transparent'));
        $tempImage->setImageFormat('png');

        // Same path-with-spaces workaround as applyTextWatermark — set the
        // resolved font on the temp image so annotateImage doesn't fall back
        // to the system default through the broken MVG codepath.
        $imageFontPath = $this->resolvedFontPathFor($watermarkData);
        if ($imageFontPath !== null) {
            try {
                $tempImage->setFont($imageFontPath);
            } catch (\Throwable $e) {
                // Non-fatal — draw still has the font set.
            }
        }

        $tempImage->annotateImage($draw, $padding, $padding + $ascender, 0, $text);
        $tempImage->rotateImage(new \ImagickPixel('transparent'), $rotation);

        $rotatedWidth  = $tempImage->getImageWidth();
        $rotatedHeight = $tempImage->getImageHeight();

        $position = $this->calculatePosition(
            $watermarkData,
            $image->getImageWidth(),
            $image->getImageHeight(),
            $rotatedWidth,
            $rotatedHeight
        );

        $image->compositeImage($tempImage, \Imagick::COMPOSITE_OVER, $position['x'], $position['y']);

        $tempImage->clear();
        $tempImage->destroy();
    }

    private function applyTextDecoration(\ImagickDraw $draw, array $watermarkData): void
    {
        $decoration = $watermarkData['watermark_text_decoration'] ?? 'none';

        switch ($decoration) {
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
     * Resolve the font path for a watermark configuration.
     *
     * Mirrors what createTextDrawObject() resolves so that the same path can be
     * applied to both ImagickDraw (via setFont) and Imagick (via setFont) — the
     * latter is needed on macOS Homebrew builds where MVG drops draw-level
     * setFont calls when the path contains spaces.
     */
    private function resolvedFontPathFor(array $watermarkData): ?string
    {
        $family = (string) ($watermarkData['watermark_font_family'] ?? 'Arial');
        $weight = (string) ($watermarkData['watermark_font_weight'] ?? 'normal');
        $style  = (string) ($watermarkData['watermark_font_style']  ?? 'normal');

        return $this->resolveFontPath($family, $weight, $style);
    }

    /**
     * Resolve a TTF font path that ImageMagick can definitely load.
     *
     * Imagick's `setFont('Arial')` only works when the font is registered with
     * the system font config. On many hosts that lookup fails silently and the
     * subsequent annotateImage() throws. Resolving an actual file path avoids
     * that failure mode entirely.
     */
    private function resolveFontPath(string $family, string $weight, string $style): ?string
    {
        /**
         * Filter the resolved font path before falling back to system defaults.
         * Pro plugins (e.g. Google Fonts integration) hook in here to provide
         * a downloaded TTF/OTF for non-system fonts.
         *
         * @param string|null $path   File path. Return non-null to short-circuit lookup.
         * @param string      $family Font family name as the user selected.
         * @param string      $weight 'normal' | 'bold' | 'lighter'
         * @param string      $style  'normal' | 'italic' | 'oblique'
         */
        $external = apply_filters('ultimate_watermark_resolve_font_path', null, $family, $weight, $style);
        if (is_string($external) && $external !== '' && file_exists($external) && is_readable($external)) {
            return $external;
        }

        // Common cross-platform font candidates by family name (descending preference).
        $candidates = $this->fontCandidatesFor($family, $weight, $style);

        foreach ($candidates as $candidate) {
            if (file_exists($candidate) && is_readable($candidate)) {
                return $candidate;
            }
        }

        // Final fallback — any TTF/OTF we can find on disk.
        return $this->getFallbackFontPath();
    }

    /**
     * Build the ordered list of TTF paths to try for a given system family.
     *
     * Each family maps to a *bundled* TTF that we ship inside the plugin
     * (assets/fonts/UltimateWatermark*.ttf, SIL OFL licensed). The bundled
     * file is always last in the list so that if the host happens to have
     * the user's actually-requested font (real Arial, real Georgia, etc.)
     * we prefer that for fidelity — but on hosts with empty font dirs we
     * still get a guaranteed render via the bundled file.
     *
     * Each system entry pairs the family with its closest bundled cousin
     * (sans → Nunito, serif → Merriweather, mono → JetBrains Mono).
     */
    private function fontCandidatesFor(string $family, string $weight, string $style): array
    {
        $isBold   = $weight === 'bold';
        $isItalic = in_array($style, ['italic', 'oblique'], true);

        $variant = '';
        if ($isBold && $isItalic) {
            $variant = 'BoldItalic';
        } elseif ($isBold) {
            $variant = 'Bold';
        } elseif ($isItalic) {
            $variant = 'Italic';
        }

        $bundled    = $this->bundledFontPath('UltimateWatermarkDefault.ttf'); // sans
        $bundledSerif = $this->bundledFontPath('UltimateWatermarkSerif.ttf');
        $bundledMono  = $this->bundledFontPath('UltimateWatermarkMono.ttf');

        $base = [
            'Arial' => [
                '/System/Library/Fonts/Supplemental/Arial.ttf',
                '/Library/Fonts/Arial.ttf',
                '/usr/share/fonts/truetype/liberation/LiberationSans-Regular.ttf',
                '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
                'C:\\Windows\\Fonts\\arial.ttf',
                $bundled,
            ],
            'Helvetica' => [
                '/System/Library/Fonts/Helvetica.ttc',
                '/usr/share/fonts/truetype/liberation/LiberationSans-Regular.ttf',
                '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
                $bundled,
            ],
            'Times New Roman' => [
                '/System/Library/Fonts/Supplemental/Times New Roman.ttf',
                '/usr/share/fonts/truetype/liberation/LiberationSerif-Regular.ttf',
                '/usr/share/fonts/truetype/dejavu/DejaVuSerif.ttf',
                'C:\\Windows\\Fonts\\times.ttf',
                $bundledSerif,
            ],
            'Georgia' => [
                '/System/Library/Fonts/Supplemental/Georgia.ttf',
                '/usr/share/fonts/truetype/dejavu/DejaVuSerif.ttf',
                'C:\\Windows\\Fonts\\georgia.ttf',
                $bundledSerif,
            ],
            'Verdana' => [
                '/System/Library/Fonts/Supplemental/Verdana.ttf',
                '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
                'C:\\Windows\\Fonts\\verdana.ttf',
                $bundled,
            ],
            'Courier New' => [
                '/System/Library/Fonts/Supplemental/Courier New.ttf',
                '/usr/share/fonts/truetype/liberation/LiberationMono-Regular.ttf',
                '/usr/share/fonts/truetype/dejavu/DejaVuSansMono.ttf',
                'C:\\Windows\\Fonts\\cour.ttf',
                $bundledMono,
            ],
        ];

        $list = $base[$family] ?? $base['Arial'];

        if ($variant === '') {
            return $list;
        }

        // Try the styled variant first by tweaking the filename, then fall back.
        $styled = [];
        foreach ($list as $path) {
            $info = pathinfo($path);
            $stem = $info['filename'] ?? '';
            $ext  = $info['extension'] ?? 'ttf';
            $dir  = $info['dirname']   ?? '.';

            // e.g. "Arial.ttf" → "Arial Bold.ttf" or "Arial-Bold.ttf"
            $styled[] = $dir . '/' . $stem . ' ' . $variant . '.' . $ext;
            $styled[] = $dir . '/' . $stem . '-' . $variant . '.' . $ext;
        }

        return array_merge($styled, $list);
    }

    /**
     * Absolute path to a TTF file inside the plugin's assets/fonts/ dir.
     */
    private function bundledFontPath(string $filename): string
    {
        return plugin_dir_path(ULTIMATE_WATERMARK_FILE) . 'assets/fonts/' . $filename;
    }

    private function getFallbackFontPath(): ?string
    {
        if (self::$fallbackFontPath !== null) {
            return self::$fallbackFontPath ?: null;
        }

        $candidates = [
            '/System/Library/Fonts/Supplemental/Arial.ttf',
            '/System/Library/Fonts/Helvetica.ttc',
            '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
            '/usr/share/fonts/truetype/liberation/LiberationSans-Regular.ttf',
            '/usr/share/fonts/TTF/DejaVuSans.ttf',
            'C:\\Windows\\Fonts\\arial.ttf',
        ];

        foreach ($candidates as $candidate) {
            if (file_exists($candidate)) {
                self::$fallbackFontPath = $candidate;
                return $candidate;
            }
        }

        // Plugin ships its own neutral TTF (Nunito 400, SIL OFL) so that text
        // watermarks render even on minimal/shared hosts where /usr/share/fonts
        // is empty and Windows/macOS fonts are obviously absent. Without this
        // guarantee, ImagickDraw::annotateImage() falls through to FreeType
        // with no font path and throws "unable to read font `'" (the exact
        // error reported on IONOS shared hosting).
        $bundled = plugin_dir_path(ULTIMATE_WATERMARK_FILE) . 'assets/fonts/UltimateWatermarkDefault.ttf';
        if (file_exists($bundled) && is_readable($bundled)) {
            self::$fallbackFontPath = $bundled;
            return $bundled;
        }

        self::$fallbackFontPath = '';
        return null;
    }

    // ---------------------------------------------------------------------
    // Image watermark
    // ---------------------------------------------------------------------

    private function applyImageWatermark(\Imagick $image, array $watermarkData): void
    {
        $watermarkPath = (string) ($watermarkData['watermark_image_path'] ?? '');
        if ($watermarkPath === '' || !file_exists($watermarkPath)) {
            return;
        }

        $watermark = new \Imagick($watermarkPath);

        try {
            $watermark = $this->resizeWatermarkImage(
                $watermark,
                $watermarkData,
                $image->getImageWidth(),
                $image->getImageHeight()
            );

            $opacity  = max(0, min(100, (int) ($watermarkData['watermark_opacity'] ?? 50)));
            $rotation = (int) ($watermarkData['watermark_rotation'] ?? 0);

            if ($opacity < 100) {
                $watermark->setImageAlphaChannel(\Imagick::ALPHACHANNEL_ACTIVATE);
                $watermark->evaluateImage(\Imagick::EVALUATE_MULTIPLY, $opacity / 100, \Imagick::CHANNEL_ALPHA);
            }

            if ($rotation !== 0) {
                $watermark->rotateImage(new \ImagickPixel('transparent'), $rotation);
            }

            $position = $this->calculatePosition(
                $watermarkData,
                $image->getImageWidth(),
                $image->getImageHeight(),
                $watermark->getImageWidth(),
                $watermark->getImageHeight()
            );

            $image->compositeImage($watermark, \Imagick::COMPOSITE_OVER, $position['x'], $position['y']);
        } finally {
            $watermark->clear();
            $watermark->destroy();
        }
    }

    private function resizeWatermarkImage(\Imagick $watermark, array $watermarkData, int $imageWidth, int $imageHeight): \Imagick
    {
        $sizeType  = $watermarkData['watermark_size_type'] ?? 'original';
        $origW     = $watermark->getImageWidth();
        $origH     = $watermark->getImageHeight();
        $newWidth  = $origW;
        $newHeight = $origH;

        switch ($sizeType) {
            case 'scaled':
                $percentage = max(1, min(100, (int) ($watermarkData['watermark_scale_percentage'] ?? 80)));
                $newWidth   = (int) ($imageWidth * $percentage / 100);
                $newHeight  = (int) ($origH * $newWidth / max(1, $origW));
                break;

            case 'custom':
                $newWidth  = (int) ($watermarkData['watermark_custom_width']  ?? $origW);
                $newHeight = (int) ($watermarkData['watermark_custom_height'] ?? $origH);
                break;

            case 'original':
            default:
                if ($origW > $imageWidth || $origH > $imageHeight) {
                    $scale     = min($imageWidth / $origW, $imageHeight / $origH);
                    $newWidth  = (int) ($origW * $scale);
                    $newHeight = (int) ($origH * $scale);
                } else {
                    return $watermark;
                }
                break;
        }

        // Final clamp to image bounds.
        if ($newWidth > $imageWidth) {
            $newHeight = (int) ($newHeight * $imageWidth / max(1, $newWidth));
            $newWidth  = $imageWidth;
        }
        if ($newHeight > $imageHeight) {
            $newWidth  = (int) ($newWidth * $imageHeight / max(1, $newHeight));
            $newHeight = $imageHeight;
        }

        $watermark->resizeImage(max(1, $newWidth), max(1, $newHeight), \Imagick::FILTER_LANCZOS, 1);
        return $watermark;
    }

    // ---------------------------------------------------------------------
    // Positioning
    // ---------------------------------------------------------------------

    private function calculatePosition(array $watermarkData, int $imageWidth, int $imageHeight, int $itemWidth, int $itemHeight): array
    {
        $position = $watermarkData['watermark_position'] ?? 'bottom-right';
        $offsetX  = (int) ($watermarkData['watermark_offset_x'] ?? 0);
        $offsetY  = (int) ($watermarkData['watermark_offset_y'] ?? 0);

        switch ($position) {
            case 'top-left':
                $x = $offsetX;
                $y = $offsetY;
                break;
            case 'top-center':
                $x = (int) (($imageWidth - $itemWidth) / 2);
                $y = $offsetY;
                break;
            case 'top-right':
                $x = $imageWidth - $itemWidth - $offsetX;
                $y = $offsetY;
                break;
            case 'center-left':
                $x = $offsetX;
                $y = (int) (($imageHeight - $itemHeight) / 2);
                break;
            case 'center':
                $x = (int) (($imageWidth - $itemWidth) / 2);
                $y = (int) (($imageHeight - $itemHeight) / 2);
                break;
            case 'center-right':
                $x = $imageWidth - $itemWidth - $offsetX;
                $y = (int) (($imageHeight - $itemHeight) / 2);
                break;
            case 'bottom-left':
                $x = $offsetX;
                $y = $imageHeight - $itemHeight - $offsetY;
                break;
            case 'bottom-center':
                $x = (int) (($imageWidth - $itemWidth) / 2);
                $y = $imageHeight - $itemHeight - $offsetY;
                break;
            case 'bottom-right':
            default:
                $x = $imageWidth - $itemWidth - $offsetX;
                $y = $imageHeight - $itemHeight - $offsetY;
                break;
        }

        // Clamp into image bounds so we never draw outside the canvas.
        $x = max(0, min($x, max(0, $imageWidth - $itemWidth)));
        $y = max(0, min($y, max(0, $imageHeight - $itemHeight)));

        return ['x' => (int) $x, 'y' => (int) $y];
    }

    private function hexToRgb(string $hex): string
    {
        $hex = ltrim($hex, '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        if (!preg_match('/^[0-9a-fA-F]{6}$/', $hex)) {
            $hex = '000000';
        }
        return sprintf('rgb(%d,%d,%d)', hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2)));
    }

    // ---------------------------------------------------------------------
    // Saving
    // ---------------------------------------------------------------------

    public function saveImage(\Imagick $image, string $outputPath, array $watermarkData = []): bool
    {
        $extension   = strtolower(pathinfo($outputPath, PATHINFO_EXTENSION));
        $quality     = max(1, min(100, (int) ($watermarkData['watermark_quality'] ?? 90)));
        $imageFormat = $watermarkData['image_format'] ?? 'baseline';

        switch ($extension) {
            case 'jpg':
            case 'jpeg':
                $image->setImageFormat('jpeg');
                $image->setImageCompressionQuality($quality);
                $image->setInterlaceScheme($imageFormat === 'progressive' ? \Imagick::INTERLACE_JPEG : \Imagick::INTERLACE_NO);
                break;

            case 'png':
                $image->setImageFormat('png');
                $image->setImageCompressionQuality($quality);
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

        $dir = dirname($outputPath);
        if (!is_dir($dir)) {
            wp_mkdir_p($dir);
        }

        return $image->writeImage($outputPath);
    }

    private function preparePreviewPath(array $watermarkData): string
    {
        $uploadDir = wp_upload_dir();
        $previewDir = trailingslashit($uploadDir['basedir']) . 'ultimate-watermark';

        if (!is_dir($previewDir)) {
            wp_mkdir_p($previewDir);
        }

        // Index the directory so listing it doesn't expose previews.
        $indexFile = $previewDir . '/index.html';
        if (!file_exists($indexFile)) {
            @file_put_contents($indexFile, '<!-- Silence is golden. -->');
        }

        // Determinstic name based on watermark fingerprint to avoid filesystem churn.
        $fingerprint = md5(wp_json_encode($watermarkData) ?: serialize($watermarkData));
        return $previewDir . '/watermark_preview_' . $fingerprint . '.png';
    }

    private function pathToUrl(string $path): string
    {
        $uploadDir = wp_upload_dir();
        $base      = wp_normalize_path($uploadDir['basedir']);
        $normPath  = wp_normalize_path($path);

        if (strpos($normPath, $base) === 0) {
            return $uploadDir['baseurl'] . substr($normPath, strlen($base));
        }

        return $uploadDir['baseurl'] . '/' . basename($path);
    }

    private function logError(string $context, \Throwable $e): void
    {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log(sprintf(
                'Ultimate Watermark Imagick %s error: %s in %s:%d',
                $context,
                $e->getMessage(),
                $e->getFile(),
                $e->getLine()
            ));
        }
    }
}
