<?php
declare(strict_types=1);

namespace SmolCms\Test\Service\Image;

use PHPUnit\Framework\MockObject\MockObject;
use SmolCms\Data\Image\ImageType;
use SmolCms\Service\File\FileSystemAccessService;
use SmolCms\Service\Image\ImageStorageService;
use SmolCms\TestUtils\Attributes\Stub;
use SmolCms\TestUtils\SimpleTestCase;

class ImageStorageServiceTest extends SimpleTestCase
{
    private const string TEST_STORAGE_DIR = '/private/test-img-storage';
    private const string TEST_FILENAME = 'test-image.png';

    private ImageStorageService $testee;
    #[Stub(FileSystemAccessService::class)]
    private FileSystemAccessService|MockObject $fileSystemAccessService;

    protected function setUp(): void
    {
        parent::setUp();
        @mkdir(ROOT_DIR . self::TEST_STORAGE_DIR, 0777, true);
        $this->fileSystemAccessService
            ->method('isPathAllowed')
            ->willReturnCallback(fn(string $path) => str_starts_with($path, ROOT_DIR . self::TEST_STORAGE_DIR));
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

    public function testStoreImage_createsDirectoryIfNotExists(): void
    {
        $image = imagecreatetruecolor(100, 100);

        $this->testee->storeImage($image, ImageType::PNG, self::TEST_FILENAME);

        self::assertDirectoryExists(ROOT_DIR . self::TEST_STORAGE_DIR);
    }

    public function testStoreImage_writesPngImage(): void
    {
        $image = imagecreatetruecolor(100, 100);
        imagefill($image, 0, 0, 0xFF0000); // Red background

        $this->testee->storeImage($image, ImageType::PNG, self::TEST_FILENAME);

        $filePath = ROOT_DIR . self::TEST_STORAGE_DIR . '/' . self::TEST_FILENAME;
        self::assertFileExists($filePath);
        self::assertStringContainsString('PNG', file_get_contents($filePath));
    }

    public function testStoreImage_writesJpegImage(): void
    {
        $image = imagecreatetruecolor(100, 100);
        imagefill($image, 0, 0, 0x00FF00); // Green background

        $this->testee->storeImage($image, ImageType::JPEG, 'test.jpg');

        $filePath = ROOT_DIR . self::TEST_STORAGE_DIR . '/test.jpg';
        self::assertFileExists($filePath);
    }

    public function testStoreImage_writesAvifImage(): void
    {
        $image = imagecreatetruecolor(100, 100);
        imagefill($image, 0, 0, 0x0000FF); // Blue background

        $this->testee->storeImage($image, ImageType::AVIF, 'test.avif');

        $filePath = ROOT_DIR . self::TEST_STORAGE_DIR . '/test.avif';
        self::assertFileExists($filePath);
    }

    public function testGetStoragePath_returnsCorrectPath(): void
    {
        $path = $this->testee->getStoragePath();
        self::assertSame(ROOT_DIR . self::TEST_STORAGE_DIR, $path);
    }
}

