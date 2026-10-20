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
        $this->assertMalformedImageRejected('invalid image data', 'Invalid JPEG image data');
    }

    public function testSanitize_emptyImageData_throwsException(): void
    {
        $this->assertMalformedImageRejected('', 'Invalid JPEG image data');
    }

    private function assertMalformedImageRejected(string $imageData, string $exceptionMessage): void
    {
        $warnings = [];
        set_error_handler(static function (int $severity, string $message) use (&$warnings): bool {
            if ($severity !== E_WARNING) {
                return false;
            }
            $warnings[] = $message;
            return true;
        });
        try {
            try {
                $this->testee->sanitize($imageData);
                self::fail('Expected malformed image input to be rejected.');
            } catch (InvalidArgumentException $exception) {
                self::assertStringContainsString($exceptionMessage, $exception->getMessage());
            }
        } finally {
            restore_error_handler();
        }

        self::assertNotEmpty($warnings, 'GD should issue a warning for malformed image data.');
        self::assertStringContainsString('imagecreatefromstring', $warnings[0]);
    }
}
