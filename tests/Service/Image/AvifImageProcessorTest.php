<?php
declare(strict_types=1);

namespace SmolCms\Test\Service\Image;

use InvalidArgumentException;
use PHPUnit\Framework\MockObject\MockObject;
use SmolCms\Service\File\FileSystemAccessService;
use SmolCms\Service\Image\AvifImageProcessor;
use SmolCms\Service\Image\ImageStorageService;
use SmolCms\TestUtils\Attributes\Stub;
use SmolCms\TestUtils\SimpleTestCase;

class AvifImageProcessorTest extends SimpleTestCase
{
    private const string TEST_STORAGE_DIR = '/private/test-avif-processor';
    private const string TEST_FILENAME = 'processed.avif';

    private AvifImageProcessor $testee;
    #[Stub(ImageStorageService::class)]
    private ImageStorageService|MockObject $storageService;
    #[Stub(FileSystemAccessService::class)]
    private FileSystemAccessService|MockObject $fileSystemAccessService;

    protected function setUp(): void
    {
        parent::setUp();
        @mkdir(ROOT_DIR . self::TEST_STORAGE_DIR, 0777, true);
        $this->fileSystemAccessService
            ->method('isPathAllowed')
            ->willReturnCallback(fn(string $path) => str_starts_with($path, ROOT_DIR . self::TEST_STORAGE_DIR));
        $this->storageService = new ImageStorageService(ROOT_DIR . self::TEST_STORAGE_DIR, $this->fileSystemAccessService);
        $this->testee = new AvifImageProcessor($this->storageService);
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

    public function testProcess_validAvifImage(): void
    {
        $avifData = file_get_contents(ROOT_DIR . '/tests/TestUtils/TestFiles/image.avif');

        $this->testee->process($avifData, self::TEST_FILENAME);

        $filePath = ROOT_DIR . self::TEST_STORAGE_DIR . '/' . self::TEST_FILENAME;
        self::assertFileExists($filePath);
        self::assertGreaterThan(0, filesize($filePath));
    }

    public function testProcess_invalidImageData_throwsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains('Invalid AVIF image data');

        @$this->testee->process('invalid image data', self::TEST_FILENAME);
    }

    public function testProcess_emptyImageData_throwsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains('Invalid AVIF image data');

        @$this->testee->process('', self::TEST_FILENAME);
    }


}

