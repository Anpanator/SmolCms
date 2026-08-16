<?php

declare(strict_types=1);

namespace SmolCms\Test\Service\Image;

use SmolCms\Data\Image\ImageType;
use SmolCms\Service\Image\JpegImageTypeDetector;
use SmolCms\TestUtils\SimpleTestCase;

class JpegImageTypeDetectorTest extends SimpleTestCase
{
    private JpegImageTypeDetector $detector;

    protected function setUp(): void
    {
        $this->detector = new JpegImageTypeDetector();
    }

    public function testDetectsValidJpeg(): void
    {
        // Valid JPEG signature: FF D8 FF
        $jpegSignature = pack('H*', 'FFD8FF');
        $this->assertEquals(ImageType::JPEG, $this->detector->getImageType($jpegSignature));
    }

    public function testRejectsInvalidJpeg(): void
    {
        $invalidContent = 'Not a JPEG file';
        $this->assertNull($this->detector->getImageType($invalidContent));
    }

    public function testRejectsPngAsJpeg(): void
    {
        // PNG signature: 89 50 4E 47 0D 0A 1A 0A
        $pngSignature = pack('H*', '89504E470D0A1A0A');
        $this->assertNull($this->detector->getImageType($pngSignature));
    }

    public function testRejectsAvifAsJpeg(): void
    {
        // AVIF signature
        $avifSignature = 'ftypavif';
        $this->assertNull($this->detector->getImageType($avifSignature));
    }

    public function testDetectsJpegWithExtraContent(): void
    {
        $jpegSignature = pack('H*', 'FFD8FF');
        $content = $jpegSignature . 'extra content here';
        $this->assertEquals(ImageType::JPEG, $this->detector->getImageType($content));
    }
}
