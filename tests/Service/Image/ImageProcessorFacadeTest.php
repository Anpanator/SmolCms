<?php
declare(strict_types=1);

namespace SmolCms\Test\Service\Image;

use GdImage;
use InvalidArgumentException;
use PHPUnit\Framework\MockObject\MockObject;
use SmolCms\Data\Image\ImageType;
use SmolCms\Service\Image\AvifImageProcessor;
use SmolCms\Service\Image\ImageProcessorFacade;
use SmolCms\Service\Image\ImageProcessorInterface;
use SmolCms\Service\Image\JpegImageProcessor;
use SmolCms\Service\Image\PngImageProcessor;
use SmolCms\TestUtils\SimpleTestCase;

class ImageProcessorFacadeTest extends SimpleTestCase
{
    private ImageProcessorFacade $testee;

    protected function setUp(): void
    {
        parent::setUp();
        $this->testee = new ImageProcessorFacade(
            new PngImageProcessor(),
            new JpegImageProcessor(),
            new AvifImageProcessor(),
        );
    }

    public function testSanitizeImage_callsOnlyTheProcessorMatchingTheType(): void
    {
        /** @var ImageProcessorInterface|MockObject $jpegProcessor */
        $jpegProcessor = $this->createMock(ImageProcessorInterface::class);
        $jpegProcessor->method('getType')->willReturn(ImageType::JPEG);
        $imageData = file_get_contents(ROOT_DIR . '/tests/TestUtils/TestFiles/image.png');
        self::assertIsString($imageData);
        $expectedImage = imagecreatetruecolor(2, 2);
        $jpegProcessor
            ->expects($this->once())
            ->method('sanitize')
            ->with($imageData)
            ->willReturn($expectedImage);
        $facade = new ImageProcessorFacade(new PngImageProcessor(), $jpegProcessor, new AvifImageProcessor());

        $result = $facade->sanitizeImage($imageData, ImageType::JPEG);

        self::assertSame($expectedImage, $result);
    }

    public function testSanitizeImage_unknownType_throwsException(): void
    {
        $facade = new ImageProcessorFacade(new PngImageProcessor());

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains('No image processor registered for type: jpeg');

        $facade->sanitizeImage('test image data', ImageType::JPEG);
    }

    public function testConstruct_duplicateProcessorForSameType_throwsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains('Duplicate image processor for type: png');

        new ImageProcessorFacade(new PngImageProcessor(), new PngImageProcessor());
    }

    /**
     * Re-encoding the sanitized image must produce a file that truly is the claimed
     * format, regardless of what format the input bytes actually were.
     */
    public function testSanitizeImage_outputReallyIsTheClaimedFormat(): void
    {
        // Input bytes are a PNG, but the detected (claimed) type is JPEG.
        $pngData = file_get_contents(ROOT_DIR . '/tests/TestUtils/TestFiles/image.png');

        $image = $this->testee->sanitizeImage($pngData, ImageType::JPEG);

        self::assertInstanceOf(GdImage::class, $image);
        self::assertStringStartsWith("\xFF\xD8\xFF", self::encodeJpeg($image));
    }

    /**
     * GD decoding reduces the upload to pure pixel data: metadata like EXIF is gone
     * once the sanitized image is encoded again.
     */
    public function testSanitizeImage_stripsExifMetadata(): void
    {
        $withExif = self::jpegWithExifApp1Segment(
            file_get_contents(ROOT_DIR . '/tests/TestUtils/TestFiles/image.jpg')
        );
        self::assertStringContainsString("Exif\x00\x00", $withExif);

        $image = $this->testee->sanitizeImage($withExif, ImageType::JPEG);

        $reEncoded = self::encodeJpeg($image);
        self::assertStringNotContainsString("Exif\x00\x00", $reEncoded);
        self::assertNotFalse(imagecreatefromstring($reEncoded));
    }

    private static function encodeJpeg(GdImage $image): string
    {
        $obLevel = ob_get_level();
        ob_start();
        try {
            $success = imagejpeg($image, null, 100);
            $content = ob_get_clean();
        } finally {
            if (ob_get_level() > $obLevel) {
                ob_end_clean();
            }
        }
        if (!$success || $content === false || $content === '') {
            self::fail('Failed to encode test image as JPEG');
        }
        return $content;
    }

    private static function jpegWithExifApp1Segment(string $jpeg): string
    {
        // Minimal but well-formed EXIF APP1 segment: "Exif\0\0" + TIFF header (big-endian,
        // IFD offset 8) + empty IFD (zero entries). Decoders ignore its (missing) content.
        $exifPayload = "Exif\x00\x00" . "MM\x00\x2A\x00\x00\x00\x08\x00\x00";
        $app1Segment = "\xFF\xE1" . pack('n', strlen($exifPayload) + 2) . $exifPayload;

        return "\xFF\xD8" . $app1Segment . substr($jpeg, 2);
    }
}
