<?php

declare(strict_types=1);

namespace SmolCms\Test\Service\Image;

use SmolCms\Data\Image\ImageType;
use SmolCms\Service\Image\AvifImageTypeDetector;
use SmolCms\Service\Image\ImageTypeDetectionFacade;
use SmolCms\Service\Image\JpegImageTypeDetector;
use SmolCms\Service\Image\PngImageTypeDetector;
use SmolCms\TestUtils\SimpleTestCase;

class ImageTypeDetectionFacadeTest extends SimpleTestCase
{
    private ImageTypeDetectionFacade $facade;

    protected function setUp(): void
    {
        $this->facade = new ImageTypeDetectionFacade(
            new PngImageTypeDetector(),
            new JpegImageTypeDetector(),
            new AvifImageTypeDetector()
        );
    }

    public function testDetectsPng(): void
    {
        $pngSignature = pack('H*', '89504E470D0A1A0A');
        $this->assertEquals(ImageType::PNG, $this->facade->getImageType($pngSignature));
    }

    public function testDetectsJpeg(): void
    {
        $jpegSignature = pack('H*', 'FFD8FF');
        $this->assertEquals(ImageType::JPEG, $this->facade->getImageType($jpegSignature));
    }

    public function testDetectsAvif(): void
    {
        $avifContent = '....ftypavif';
        $this->assertEquals(ImageType::AVIF, $this->facade->getImageType($avifContent));
    }

    public function testReturnsNullForUnknownType(): void
    {
        $unknownContent = 'Unknown file content';
        $this->assertNull($this->facade->getImageType($unknownContent));
    }

    public function testCanHandleShortContent(): void
    {
        $this->assertNull($this->facade->getImageType('abc'));
    }

    public function testCanHandleEmptyContent(): void
    {
        $this->assertNull($this->facade->getImageType(''));
    }
}
