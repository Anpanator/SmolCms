<?php

declare(strict_types=1);

namespace SmolCms\Test\Service\Image;

use SmolCms\Data\Image\ImageType;
use SmolCms\Service\Image\WebpImageTypeDetector;
use SmolCms\TestUtils\SimpleTestCase;

class WebpImageTypeDetectorTest extends SimpleTestCase
{
    private WebpImageTypeDetector $detector;

    protected function setUp(): void
    {
        parent::setUp();
        $this->detector = new WebpImageTypeDetector();
    }

    public function testDetectsValidWebp(): void
    {
        // Valid WebP signature: RIFF container marker at offset 0, WEBP tag at offset 8
        $webpContent = 'RIFF' . "\x00\x00\x00\x00" . 'WEBP';
        $this->assertEquals(ImageType::WEBP, $this->detector->getImageType($webpContent));
    }

    public function testDetectsWebpWithExtraContent(): void
    {
        $webpContent = 'RIFF' . "\x00\x00\x00\x00" . 'WEBP' . 'extra content here';
        $this->assertEquals(ImageType::WEBP, $this->detector->getImageType($webpContent));
    }

    public function testRejectsInvalidWebp(): void
    {
        $invalidContent = 'Not a WebP file';
        $this->assertNull($this->detector->getImageType($invalidContent));
    }

    public function testRejectsRiffContainerWithoutWebpTag(): void
    {
        // RIFF container of another flavor (e.g. WAV): no WEBP tag at offset 8
        $wavContent = 'RIFF' . "\x00\x00\x00\x00" . 'WAVE';
        $this->assertNull($this->detector->getImageType($wavContent));
    }

    public function testRejectsPngAsWebp(): void
    {
        // PNG signature: 89 50 4E 47 0D 0A 1A 0A
        $pngSignature = pack('H*', '89504E470D0A1A0A');
        $this->assertNull($this->detector->getImageType($pngSignature));
    }

    public function testRejectsJpegAsWebp(): void
    {
        // JPEG signature: FF D8 FF
        $jpegSignature = pack('H*', 'FFD8FF');
        $this->assertNull($this->detector->getImageType($jpegSignature));
    }

    public function testRejectsShortContent(): void
    {
        $this->assertNull($this->detector->getImageType('RIFF.WEB'));
    }
}
