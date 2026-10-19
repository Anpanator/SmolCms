<?php
declare(strict_types=1);

namespace SmolCms\Test\Service\Image;

use PHPUnit\Framework\MockObject\MockObject;
use RuntimeException;
use SmolCms\Data\Image\ImageType;
use SmolCms\Service\File\FileSystemAccessService;
use SmolCms\Service\Image\ImageStorageService;
use SmolCms\Service\Image\StoredImage;
use SmolCms\TestUtils\Attributes\Stub;
use SmolCms\TestUtils\SimpleTestCase;

class ImageStorageServiceTest extends SimpleTestCase
{
    private const string TEST_STORAGE_DIR = '/private/test-img-storage';

    private ImageStorageService $testee;
    #[Stub(FileSystemAccessService::class)]
    private FileSystemAccessService|MockObject $fileSystemAccessService;
    private bool $storageAllowed = true;

    protected function setUp(): void
    {
        parent::setUp();
        @mkdir(ROOT_DIR . self::TEST_STORAGE_DIR, 0777, true);
        $this->storageAllowed = true;
        $this->fileSystemAccessService
            ->method('isPathAllowed')
            ->willReturnCallback(
                fn(string $path) => $this->storageAllowed
                    && str_starts_with($path, ROOT_DIR . self::TEST_STORAGE_DIR)
            );
        $this->testee = new ImageStorageService(ROOT_DIR . self::TEST_STORAGE_DIR, $this->fileSystemAccessService);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        self::rmDirRecursive(ROOT_DIR . self::TEST_STORAGE_DIR);
    }

    private static function rmDirRecursive(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }
        $items = scandir($path);
        if ($items === false) {
            return;
        }
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $itemPath = $path . '/' . $item;
            if (is_dir($itemPath)) {
                self::rmDirRecursive($itemPath);
            } else {
                unlink($itemPath);
            }
        }
        rmdir($path);
    }

    public function testStoreImage_createsTwoCharSubdirectoryIfNotExists(): void
    {
        $image = imagecreatetruecolor(100, 100);

        $stored = $this->testee->storeImage($image, ImageType::PNG);

        self::assertDirectoryExists(
            ROOT_DIR . self::TEST_STORAGE_DIR . '/' . substr($stored->getChecksum(), 0, 2)
        );
    }

    public function testStoreImage_writesChecksumNamedPngIntoTwoCharSubdirectory(): void
    {
        $image = imagecreatetruecolor(100, 100);
        imagefill($image, 0, 0, 0xFF0000); // Red background

        $stored = $this->testee->storeImage($image, ImageType::PNG);

        self::assertMatchesRegularExpression('/^[0-9a-f]{64}\.png$/', $stored->getFilename());
        self::assertSame(substr($stored->getChecksum(), 0, 2) . '/' . $stored->getFilename(), $stored->getRelativePath());

        $filePath = ROOT_DIR . self::TEST_STORAGE_DIR . '/' . $stored->getRelativePath();
        $content = file_get_contents($filePath);
        self::assertFileExists($filePath);
        self::assertStringContainsString('PNG', $content);
        self::assertSame($stored->getChecksum(), hash('sha256', $content));
        self::assertSame(strlen($content), $stored->getFileSize());
        self::assertSame(100, $stored->getWidth());
        self::assertSame(100, $stored->getHeight());
        self::assertSame(ImageType::PNG, $stored->getType());
    }

    public function testStoreImage_writesJpegContent(): void
    {
        $image = imagecreatetruecolor(100, 100);
        imagefill($image, 0, 0, 0x00FF00); // Green background

        $stored = $this->testee->storeImage($image, ImageType::JPEG);

        self::assertMatchesRegularExpression('/^[0-9a-f]{64}\.jpeg$/', $stored->getFilename());
        $filePath = ROOT_DIR . self::TEST_STORAGE_DIR . '/' . $stored->getRelativePath();
        self::assertFileExists($filePath);
        self::assertStringStartsWith("\xFF\xD8\xFF", file_get_contents($filePath));
    }

    public function testStoreImage_writesAvifContent(): void
    {
        $image = imagecreatetruecolor(100, 100);
        imagefill($image, 0, 0, 0x0000FF); // Blue background

        $stored = $this->testee->storeImage($image, ImageType::AVIF);

        self::assertMatchesRegularExpression('/^[0-9a-f]{64}\.avif$/', $stored->getFilename());
        $filePath = ROOT_DIR . self::TEST_STORAGE_DIR . '/' . $stored->getRelativePath();
        self::assertFileExists($filePath);
        self::assertSame('ftyp', substr(file_get_contents($filePath), 4, 4));
    }

    public function testStoreImage_sameContent_storesSameFileName(): void
    {
        $image = imagecreatetruecolor(50, 50);

        $first = $this->testee->storeImage($image, ImageType::PNG);
        $second = $this->testee->storeImage($image, ImageType::PNG);

        self::assertSame($first->getChecksum(), $second->getChecksum());
        self::assertSame($first->getFilename(), $second->getFilename());
        self::assertFileExists(ROOT_DIR . self::TEST_STORAGE_DIR . '/' . $second->getRelativePath());
    }

    public function testStoreImage_disallowedDirectory_throwsException(): void
    {
        $this->storageAllowed = false;
        $image = imagecreatetruecolor(10, 10);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageIsOrContains('Image storage is not allowed in path. Check config!');

        $this->testee->storeImage($image, ImageType::PNG);
    }

    public function testGetStoragePath_returnsCorrectPath(): void
    {
        $path = $this->testee->getStoragePath();
        self::assertSame(ROOT_DIR . self::TEST_STORAGE_DIR, $path);
    }
}
