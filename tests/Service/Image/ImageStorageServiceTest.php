<?php
declare(strict_types=1);

namespace SmolCms\Test\Service\Image;

use RuntimeException;
use SmolCms\Data\Image\ImageType;
use SmolCms\Exception\FileAccessException;
use SmolCms\Service\File\FileSystemAccessService;
use SmolCms\Service\Image\ImageStorageService;
use SmolCms\Service\Image\StoredImage;
use SmolCms\TestUtils\SimpleTestCase;

class ImageStorageServiceTest extends SimpleTestCase
{
    private const string TEST_STORAGE_DIR = '/private/test-img-storage';

    private ImageStorageService $testee;
    private FileSystemAccessService $fileSystemAccessService;

    protected function setUp(): void
    {
        parent::setUp();
        if (!is_dir(ROOT_DIR . self::TEST_STORAGE_DIR)
            && !mkdir(ROOT_DIR . self::TEST_STORAGE_DIR, 0777, true)
            && !is_dir(ROOT_DIR . self::TEST_STORAGE_DIR)) {
            throw new RuntimeException('Could not create image storage directory for tests.');
        }
        $this->fileSystemAccessService = new FileSystemAccessService(ROOT_DIR . self::TEST_STORAGE_DIR);
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

    public function testStoreOriginal_writesChecksumNamedFileDirectlyInStorageRoot(): void
    {
        $image = imagecreatetruecolor(20, 10);
        ob_start();
        $success = imagepng($image);
        $content = ob_get_clean();
        self::assertTrue($success);
        self::assertIsString($content);

        $stored = $this->testee->storeOriginal($content, ImageType::PNG, $image);

        self::assertSame($stored->getFilename(), $stored->getRelativePath());
        self::assertFileExists(ROOT_DIR . self::TEST_STORAGE_DIR . '/' . $stored->getFilename());
        self::assertDirectoryDoesNotExist(
            ROOT_DIR . self::TEST_STORAGE_DIR . '/' . substr($stored->getChecksum(), 0, 2)
        );
        self::assertSame($content, file_get_contents(ROOT_DIR . self::TEST_STORAGE_DIR . '/' . $stored->getFilename()));
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
        $allowedDirectory = ROOT_DIR . self::TEST_STORAGE_DIR . '/allowed';
        $disallowedDirectory = ROOT_DIR . self::TEST_STORAGE_DIR . '/disallowed';
        if (!mkdir($allowedDirectory) || !mkdir($disallowedDirectory)) {
            self::fail('Could not create directories for the whitelist test.');
        }
        $restrictedFileSystemAccessService = new FileSystemAccessService($allowedDirectory);
        $restrictedTestee = new ImageStorageService($disallowedDirectory, $restrictedFileSystemAccessService);
        $image = imagecreatetruecolor(10, 10);

        $this->expectException(FileAccessException::class);
        $this->expectExceptionMessage('is not allowed');

        $restrictedTestee->storeImage($image, ImageType::PNG);
    }

    public function testStoreBytes_refusesToWriteThroughSymlink(): void
    {
        $relativePath = 'ab/symlink-target.png';
        $symlinkPath = ROOT_DIR . self::TEST_STORAGE_DIR . '/' . $relativePath;
        $targetPath = ROOT_DIR . self::TEST_STORAGE_DIR . '/target-content.txt';
        if (!is_dir(dirname($symlinkPath)) && !mkdir(dirname($symlinkPath), 0777, true) && !is_dir(dirname($symlinkPath))) {
            self::fail('Could not create image storage shard for symlink test.');
        }
        self::assertNotFalse(file_put_contents($targetPath, 'original target content'));
        self::assertTrue(symlink($targetPath, $symlinkPath));

        try {
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessageIsOrContains('Refusing to write through symlink at path');
            $this->testee->storeBytes('replacement content', $relativePath);
        } finally {
            self::assertSame('original target content', file_get_contents($targetPath));
            self::assertTrue(is_link($symlinkPath));
        }
    }

    public function testGetStoragePath_returnsCorrectPath(): void
    {
        $path = $this->testee->getStoragePath();
        self::assertSame(ROOT_DIR . self::TEST_STORAGE_DIR, $path);
    }
}
