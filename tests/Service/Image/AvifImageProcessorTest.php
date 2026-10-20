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
        $this->assertMalformedImageRejected('invalid image data', 'Invalid AVIF image data');
    }

    public function testSanitize_emptyImageData_throwsException(): void
    {
        $this->assertMalformedImageRejected('', 'Invalid AVIF image data');
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
