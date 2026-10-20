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
        $this->assertMalformedImageRejected('invalid image data', 'Invalid PNG image data');
    }

    public function testSanitize_emptyImageData_throwsException(): void
    {
        $this->assertMalformedImageRejected('', 'Invalid PNG image data');
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
