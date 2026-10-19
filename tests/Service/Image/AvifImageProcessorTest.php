<?php
declare(strict_types=1);

namespace SmolCms\Test\Service\Image;

use GdImage;
use InvalidArgumentException;
use SmolCms\Data\Image\ImageType;
use SmolCms\Service\Image\AvifImageProcessor;
use SmolCms\TestUtils\SimpleTestCase;

class AvifImageProcessorTest extends SimpleTestCase
{
    private AvifImageProcessor $testee;

    protected function setUp(): void
    {
        parent::setUp();
        $this->testee = new AvifImageProcessor();
    }

    public function testGetType_returnsAvif(): void
    {
        self::assertSame(ImageType::AVIF, $this->testee->getType());
    }

    public function testSanitize_validAvifImage_returnsGdImage(): void
    {
        $avifData = file_get_contents(ROOT_DIR . '/tests/TestUtils/TestFiles/image.avif');

        $image = $this->testee->sanitize($avifData);

        self::assertInstanceOf(GdImage::class, $image);
    }

    public function testSanitize_invalidImageData_throwsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains('Invalid AVIF image data');

        @$this->testee->sanitize('invalid image data');
    }

    public function testSanitize_emptyImageData_throwsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains('Invalid AVIF image data');

        @$this->testee->sanitize('');
    }
}
