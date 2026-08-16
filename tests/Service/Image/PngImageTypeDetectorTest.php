<?php

declare(strict_types=1);

namespace SmolCms\Test\Service\Image;

use SmolCms\Data\Image\ImageType;
use SmolCms\Service\Image\PngImageTypeDetector;
use SmolCms\TestUtils\SimpleTestCase;

class PngImageTypeDetectorTest extends SimpleTestCase
{
    private PngImageTypeDetector $detector;

    protected function setUp(): void
    {
        $this->detector = new PngImageTypeDetector();
    }

    public function testDetectsValidPng(): void
    {
        // Valid PNG signature: 89 50 4E 47 0D 0A 1A 0A
        $pngSignature = pack('H*', '89504E470D0A1A0A');
        $this->assertEquals(ImageType::PNG, $this->detector->getImageType($pngSignature));
    }

    public function testRejectsInvalidPng(): void
    {
        $invalidContent = 'Not a PNG file';
        $this->assertNull($this->detector->getImageType($invalidContent));
    }

    public function testRejectsJpegAsPng(): void
    {
        // JPEG signature: FF D8 FF
        $jpegSignature = pack('H*', 'FFD8FF');
        $this->assertNull($this->detector->getImageType($jpegSignature));
    }

    public function testRejectsAvifAsPng(): void
    {
        // AVIF signature
        $avifSignature = 'ftypavif';
        $this->assertNull($this->detector->getImageType($avifSignature));
    }

    public function testDetectsPngWithExtraContent(): void
    {
        $pngSignature = pack('H*', '89504E470D0A1A0A');
        $content = $pngSignature . 'extra content here';
        $this->assertEquals(ImageType::PNG, $this->detector->getImageType($content));
    }
}
