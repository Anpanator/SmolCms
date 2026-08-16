<?php

declare(strict_types=1);

namespace SmolCms\Test\Service\Image;

use SmolCms\Data\Image\ImageType;
use SmolCms\Service\Image\AvifImageTypeDetector;
use SmolCms\TestUtils\SimpleTestCase;

class AvifImageTypeDetectorTest extends SimpleTestCase
{
    private AvifImageTypeDetector $detector;

    protected function setUp(): void
    {
        $this->detector = new AvifImageTypeDetector();
    }

    public function testDetectsValidAvif(): void
    {
        // Valid AVIF signature: ftypavif (brand marker at offset 4)
        $avifContent = '....ftypavif';
        $this->assertEquals(ImageType::AVIF, $this->detector->getImageType($avifContent));
    }

    public function testDetectsValidAvifWithBrand(): void
    {
        // AVIF with brand variant: ftypavis
        $avifContent = '....ftypavis';
        $this->assertEquals(ImageType::AVIF, $this->detector->getImageType($avifContent));
    }

    public function testRejectsInvalidAvif(): void
    {
        $invalidContent = 'Not an AVIF file';
        $this->assertNull($this->detector->getImageType($invalidContent));
    }

    public function testRejectsPngAsAvif(): void
    {
        // PNG signature: 89 50 4E 47 0D 0A 1A 0A
        $pngSignature = pack('H*', '89504E470D0A1A0A');
        $this->assertNull($this->detector->getImageType($pngSignature));
    }

    public function testRejectsJpegAsAvif(): void
    {
        // JPEG signature: FF D8 FF
        $jpegSignature = pack('H*', 'FFD8FF');
        $this->assertNull($this->detector->getImageType($jpegSignature));
    }

    public function testDetectsAvifWithExtraContent(): void
    {
        $avifContent = '....ftypavif';
        $content = $avifContent . 'extra content here';
        $this->assertEquals(ImageType::AVIF, $this->detector->getImageType($content));
    }
}
