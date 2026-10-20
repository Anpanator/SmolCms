<?php

declare(strict_types=1);

namespace SmolCms\Test\Service\Image;

use GdImage;
use SmolCms\Data\Image\ImageType;
use SmolCms\Service\Image\ImageDimensions;
use SmolCms\TestUtils\SimpleTestCase;

class ImageDimensionsTest extends SimpleTestCase
{
    public function testFromStringAcceptsValidImageWithinLimits(): void
    {
        $imageData = file_get_contents(ROOT_DIR . '/tests/TestUtils/TestFiles/image.png');
        self::assertIsString($imageData);

        $dimensions = ImageDimensions::fromString($imageData);

        self::assertNotNull($dimensions);
        self::assertGreaterThan(0, $dimensions['width']);
        self::assertGreaterThan(0, $dimensions['height']);
        self::assertTrue(ImageDimensions::areWithinLimits($dimensions['width'], $dimensions['height']));
    }

    public function testFromStringRejectsAnImageOverTheMaximumDimension(): void
    {
        $image = imagecreatetruecolor(ImageDimensions::MAX_DIMENSION + 1, 1);
        self::assertInstanceOf(GdImage::class, $image);
        $imageData = self::encodePng($image);

        self::assertNull(ImageDimensions::fromString($imageData));
    }

    public function testAreWithinLimitsRejectsAnImageOverTheTotalPixelBudget(): void
    {
        self::assertFalse(ImageDimensions::areWithinLimits(5_000, 4_100));
    }

    private static function encodePng(GdImage $image): string
    {
        $obLevel = ob_get_level();
        ob_start();
        try {
            $success = imagepng($image);
            $content = ob_get_clean();
        } finally {
            if (ob_get_level() > $obLevel) {
                ob_end_clean();
            }
        }
        if (!$success || $content === false || $content === '') {
            self::fail('Failed to encode test image as PNG');
        }
        return $content;
    }
}
