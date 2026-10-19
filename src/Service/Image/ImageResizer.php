<?php

declare(strict_types=1);

namespace SmolCms\Service\Image;

use GdImage;
use InvalidArgumentException;
use RuntimeException;

/**
 * Downscaling-only resize helper.
 *
 * Target dimensions are computed with pure integer arithmetic (exact, no float rounding
 * errors at "clean" ratios) so that the resized image always fits the given box while
 * keeping the aspect ratio. Images already inside the box are never upscaled.
 */
final class ImageResizer
{
    private function __construct()
    {
    }

    /**
     * Returns the image scaled down to fit within the given box, keeping the aspect ratio.
     * An image that already fits is returned unchanged (the same instance, no re-encode).
     */
    public static function resizeToFit(GdImage $image, int $maxWidth, int $maxHeight): GdImage
    {
        $originalWidth = imagesx($image);
        $originalHeight = imagesy($image);
        [$targetWidth, $targetHeight] =
            self::calculateFitDimensions($originalWidth, $originalHeight, $maxWidth, $maxHeight);
        if ($targetWidth === $originalWidth && $targetHeight === $originalHeight) {
            return $image;
        }

        $scaled = imagescale($image, $targetWidth, $targetHeight);
        if ($scaled === false) {
            throw new RuntimeException("Failed to resize image to {$targetWidth}x{$targetHeight}.");
        }
        return $scaled;
    }

    /**
     * Largest dimensions that fit within the box while preserving the aspect ratio.
     * A fitting image is passed through unchanged (no upscaling); extreme aspect ratios
     * are clamped to at least one pixel per side.
     *
     * @return array{0: int, 1: int} The fitted [width, height].
     */
    public static function calculateFitDimensions(
        int $originalWidth,
        int $originalHeight,
        int $maxWidth,
        int $maxHeight,
    ): array
    {
        if ($originalWidth < 1 || $originalHeight < 1) {
            throw new InvalidArgumentException('Image dimensions must be positive.');
        }
        if ($maxWidth < 1 || $maxHeight < 1) {
            throw new InvalidArgumentException('Maximum box dimensions must be positive.');
        }
        if ($originalWidth <= $maxWidth && $originalHeight <= $maxHeight) {
            return [$originalWidth, $originalHeight];
        }

        // Width is the limiting side when maxW/origW <= maxH/origH; the comparison is
        // cross-multiplied so it stays in exact integer space.
        if ($originalWidth * $maxHeight >= $originalHeight * $maxWidth) {
            return [$maxWidth, max(1, intdiv($originalHeight * $maxWidth, $originalWidth))];
        }
        return [max(1, intdiv($originalWidth * $maxHeight, $originalHeight)), $maxHeight];
    }
}
