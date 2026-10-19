<?php

declare(strict_types=1);

namespace SmolCms\Test\Service\Image;

use GdImage;
use InvalidArgumentException;
use SmolCms\Data\Image\ImageType;
use SmolCms\Service\Image\WebpImageProcessor;
use SmolCms\TestUtils\SimpleTestCase;

class WebpImageProcessorTest extends SimpleTestCase
{
    private WebpImageProcessor $testee;

    protected function setUp(): void
    {
        parent::setUp();
        $this->testee = new WebpImageProcessor();
    }

    public function testGetType_returnsWebp(): void
    {
        self::assertSame(ImageType::WEBP, $this->testee->getType());
    }

    public function testSanitize_validWebpImage_returnsGdImage(): void
    {
        $webpData = self::generateWebp(50, 50);

        $image = $this->testee->sanitize($webpData);

        self::assertInstanceOf(GdImage::class, $image);
        self::assertSame(50, imagesx($image));
        self::assertSame(50, imagesy($image));
    }

    public function testSanitize_invalidImageData_throwsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains('Invalid WEBP image data');

        @$this->testee->sanitize('invalid image data');
    }

    public function testSanitize_emptyImageData_throwsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains('Invalid WEBP image data');

        @$this->testee->sanitize('');
    }

    public static function generateWebp(int $width, int $height, int $color = 0xFF0000): string
    {
        $image = imagecreatetruecolor($width, $height);
        imagefill($image, 0, 0, $color);

        $obLevel = ob_get_level();
        ob_start();
        try {
            $success = imagewebp($image, null, 100);
            $content = ob_get_clean();
        } finally {
            if (ob_get_level() > $obLevel) {
                ob_end_clean();
            }
        }
        if (!$success || $content === false || $content === '') {
            self::fail('Failed to generate test WebP image');
        }
        return $content;
    }
}
