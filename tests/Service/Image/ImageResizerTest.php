<?php

declare(strict_types=1);

namespace SmolCms\Test\Service\Image;

use GdImage;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use SmolCms\Service\Image\ImageResizer;
use SmolCms\TestUtils\SimpleTestCase;

class ImageResizerTest extends SimpleTestCase
{
    #[DataProvider('provideCalculateFitDimensionsCases')]
    public function testCalculateFitDimensions(
        int $originalWidth,
        int $originalHeight,
        int $maxWidth,
        int $maxHeight,
        array $expected,
    ): void {
        self::assertSame(
            $expected,
            ImageResizer::calculateFitDimensions($originalWidth, $originalHeight, $maxWidth, $maxHeight)
        );
    }

    public static function provideCalculateFitDimensionsCases(): array
    {
        return [
            'landscape downscales to fit width' => [
                800, 400, 512, 512, [512, 256],
            ],
            'portrait downscales to fit height' => [
                400, 800, 512, 512, [256, 512],
            ],
            'square downscales to the box' => [
                600, 600, 512, 512, [512, 512],
            ],
            'already small is unchanged' => [
                100, 50, 512, 512, [100, 50],
            ],
            'smaller than the box is never upscaled' => [
                10, 5, 512, 512, [10, 5],
            ],
            'fitting exactly on one edge is unchanged' => [
                512, 100, 512, 512, [512, 100],
            ],
            'non-square box limits height' => [
                1000, 500, 2000, 100, [200, 100],
            ],
            'non-square box limits width' => [
                1000, 500, 100, 100, [100, 50],
            ],
            'extreme aspect ratio clamps to one pixel' => [
                10000, 1, 512, 512, [512, 1],
            ],
            'extreme portrait aspect ratio clamps to one pixel' => [
                1, 10000, 512, 512, [1, 512],
            ],
            'clean ratio has no rounding drift' => [
                1024, 768, 512, 512, [512, 384],
            ],
        ];
    }

    public function testCalculateFitDimensions_zeroImageDimension_throwsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains('Image dimensions must be positive.');

        ImageResizer::calculateFitDimensions(0, 10, 512, 512);
    }

    public function testCalculateFitDimensions_zeroBoxDimension_throwsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains('Maximum box dimensions must be positive.');

        ImageResizer::calculateFitDimensions(10, 10, 0, 512);
    }

    public function testResizeToFit_imageWithinBounds_returnsSameInstance(): void
    {
        $image = imagecreatetruecolor(100, 50);

        $result = ImageResizer::resizeToFit($image, 512, 512);

        self::assertSame($image, $result);
        self::assertSame(100, imagesx($result));
        self::assertSame(50, imagesy($result));
    }

    public function testResizeToFit_landscapeImage_scalesDownKeepingAspectRatio(): void
    {
        $image = imagecreatetruecolor(800, 400);

        $result = ImageResizer::resizeToFit($image, 512, 512);

        self::assertNotSame($image, $result);
        self::assertSame(512, imagesx($result));
        self::assertSame(256, imagesy($result));
    }

    public function testResizeToFit_portraitImage_scalesDownKeepingAspectRatio(): void
    {
        $image = imagecreatetruecolor(400, 800);

        $result = ImageResizer::resizeToFit($image, 512, 512);

        self::assertSame(256, imagesx($result));
        self::assertSame(512, imagesy($result));
    }

    public function testResizeToFit_nonSquareBox_scalesDownToFitBothSides(): void
    {
        $image = imagecreatetruecolor(640, 320);

        $result = ImageResizer::resizeToFit($image, 100, 100);

        self::assertSame(100, imagesx($result));
        self::assertSame(50, imagesy($result));
    }

    public function testResizeToFit_scaledImage_staysWithinTheBox(): void
    {
        $image = imagecreatetruecolor(1000, 333);

        $result = ImageResizer::resizeToFit($image, 512, 512);

        self::assertLessThanOrEqual(512, imagesx($result));
        self::assertLessThanOrEqual(512, imagesy($result));
        self::assertGreaterThan(0, imagesx($result));
        self::assertGreaterThan(0, imagesy($result));
    }
}
