<?php
declare(strict_types=1);

namespace SmolCms\Test\Service\Image;

use GdImage;
use InvalidArgumentException;
use SmolCms\Data\Image\ImageType;
use SmolCms\Service\Image\PngImageProcessor;
use SmolCms\TestUtils\SimpleTestCase;

class PngImageProcessorTest extends SimpleTestCase
{
    private PngImageProcessor $testee;

    protected function setUp(): void
    {
        parent::setUp();
        $this->testee = new PngImageProcessor();
    }

    public function testGetType_returnsPng(): void
    {
        self::assertSame(ImageType::PNG, $this->testee->getType());
    }

    public function testSanitize_validPngImage_returnsGdImage(): void
    {
        $pngData = file_get_contents(ROOT_DIR . '/tests/TestUtils/TestFiles/image.png');

        $image = $this->testee->sanitize($pngData);

        self::assertInstanceOf(GdImage::class, $image);
        self::assertSame(getimagesize(ROOT_DIR . '/tests/TestUtils/TestFiles/image.png')[0], imagesx($image));
        self::assertSame(getimagesize(ROOT_DIR . '/tests/TestUtils/TestFiles/image.png')[1], imagesy($image));
    }

    public function testSanitize_invalidImageData_throwsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains('Invalid PNG image data');

        @$this->testee->sanitize('invalid image data');
    }

    public function testSanitize_emptyImageData_throwsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains('Invalid PNG image data');

        @$this->testee->sanitize('');
    }
}
