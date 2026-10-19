<?php
declare(strict_types=1);

namespace SmolCms\Test\Service\Image;

use GdImage;
use InvalidArgumentException;
use SmolCms\Data\Image\ImageType;
use SmolCms\Service\Image\JpegImageProcessor;
use SmolCms\TestUtils\SimpleTestCase;

class JpegImageProcessorTest extends SimpleTestCase
{
    private JpegImageProcessor $testee;

    protected function setUp(): void
    {
        parent::setUp();
        $this->testee = new JpegImageProcessor();
    }

    public function testGetType_returnsJpeg(): void
    {
        self::assertSame(ImageType::JPEG, $this->testee->getType());
    }

    public function testSanitize_validJpegImage_returnsGdImage(): void
    {
        $jpegData = file_get_contents(ROOT_DIR . '/tests/TestUtils/TestFiles/image.jpg');

        $image = $this->testee->sanitize($jpegData);

        self::assertInstanceOf(GdImage::class, $image);
    }

    public function testSanitize_invalidImageData_throwsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains('Invalid JPEG image data');

        @$this->testee->sanitize('invalid image data');
    }

    public function testSanitize_emptyImageData_throwsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains('Invalid JPEG image data');

        @$this->testee->sanitize('');
    }
}
