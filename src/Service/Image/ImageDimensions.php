<?php

declare(strict_types=1);

namespace SmolCms\Service\Image;

use ErrorException;
use Throwable;

final class ImageDimensions
{
    public const int MAX_DIMENSION = 16_384;
    public const int MAX_TOTAL_PIXELS = 20_000_000;

    private function __construct()
    {
    }

    /** @return array{width: int, height: int}|null */
    public static function fromString(string $imageData): ?array
    {
        return self::probe(static fn() => getimagesizefromstring($imageData));
    }

    /** @return array{width: int, height: int}|null */
    public static function fromFile(string $filePath): ?array
    {
        return self::probe(static fn() => getimagesize($filePath));
    }

    public static function areWithinLimits(int $width, int $height): bool
    {
        return $width > 0
            && $height > 0
            && $width <= self::MAX_DIMENSION
            && $height <= self::MAX_DIMENSION
            && $width <= intdiv(self::MAX_TOTAL_PIXELS, $height);
    }

    /** @return array{width: int, height: int}|null */
    private static function probe(callable $probe): ?array
    {
        set_error_handler(
            static function (int $severity, string $message, string $file, int $line): never {
                throw new ErrorException($message, 0, $severity, $file, $line);
            }
        );
        try {
            $imageInfo = $probe();
        } catch (Throwable) {
            return null;
        } finally {
            restore_error_handler();
        }

        $width = is_array($imageInfo) ? ($imageInfo[0] ?? null) : null;
        $height = is_array($imageInfo) ? ($imageInfo[1] ?? null) : null;
        if (!is_int($width) || !is_int($height) || !self::areWithinLimits($width, $height)) {
            return null;
        }

        return ['width' => $width, 'height' => $height];
    }
}
