<?php
declare(strict_types=1);

namespace SmolCms\Test\Service\File;

use SmolCms\Exception\FileAccessException;
use SmolCms\Service\File\FileSystemAccessService;
use SmolCms\TestUtils\SimpleTestCase;

class FileSystemAccessServiceTest extends SimpleTestCase
{
    private const string TEST_DIR = '/private/test-fs-access';
    private const string EMPTY_DIR = '/private/test-fs-access/empty';
    private const string TEST_FILE = '/private/test-fs-access/test.txt';
    private const string FORBIDDEN_PATH = '/etc/passwd';
    private const string FORBIDDEN_FILE_THAT_EXISTS = ROOT_DIR . '/tests/TestUtils/TestFiles/forbidden.txt';

    private FileSystemAccessService $testee;

    protected function setUp(): void
    {
        parent::setUp();
        $this->testee = new FileSystemAccessService();
        @mkdir(ROOT_DIR . self::EMPTY_DIR, 0777, true);
        file_put_contents(ROOT_DIR . self::TEST_FILE, 'hello world');
        file_put_contents(self::FORBIDDEN_FILE_THAT_EXISTS, 'I exist');
    }

    protected function tearDown(): void
    {
        self::rmDirRecursive(ROOT_DIR . self::TEST_DIR);
    }

    private static function rmDirRecursive(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }
        $items = scandir($path);
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

    public function testReadFile_success(): void
    {
        $result = $this->testee->readFile(ROOT_DIR . self::TEST_FILE);
        self::assertSame('hello world', $result);
    }

    public function testReadFile_throwsWhenFileDoesNotExist(): void
    {
        $this->expectException(FileAccessException::class);
        $this->testee->readFile(ROOT_DIR . self::TEST_DIR . '/nonexistent.txt');
    }

    public function testReadFile_throwsWhenPathNotAllowed(): void
    {
        $this->expectException(FileAccessException::class);
        $this->testee->readFile(self::FORBIDDEN_PATH);
    }

    public function testFileExists_returnsTrueForExistingFile(): void
    {
        $result = $this->testee->fileExists(ROOT_DIR . self::TEST_FILE);
        self::assertTrue($result);
    }

    public function testFileExists_returnsFalseForMissingFile(): void
    {
        $result = $this->testee->fileExists(ROOT_DIR . self::TEST_DIR . '/nonexistent.txt');
        self::assertFalse($result);
    }

    public function testFileExists_throwsWhenParentDirectoryNotResolvable(): void
    {
        $this->expectException(FileAccessException::class);
        $this->testee->fileExists('/nonexistent-parent-dir-12345/file.txt');
    }

    public function testDeleteFile_success(): void
    {
        $file = ROOT_DIR . self::TEST_DIR . '/to-delete.txt';
        file_put_contents($file, 'delete me');

        $this->testee->deleteFile($file);

        self::assertFileDoesNotExist($file);
    }

    public function testDeleteFile_throwsWhenFileDoesNotExist(): void
    {
        $this->expectException(FileAccessException::class);
        $this->testee->deleteFile(ROOT_DIR . self::TEST_DIR . '/nonexistent.txt');
    }

    public function testDeleteFile_throwsWhenPathNotAllowed(): void
    {
        $this->expectException(FileAccessException::class);
        $this->testee->deleteFile(self::FORBIDDEN_FILE_THAT_EXISTS);
    }

    public function testListFiles_returnsEmptyArrayForEmptyDirectory(): void
    {
        $result = $this->testee->listFiles(ROOT_DIR . self::EMPTY_DIR);
        self::assertSame([], $result);
    }

    public function testListFiles_throwsWhenNotADirectory(): void
    {
        $this->expectException(FileAccessException::class);
        $this->testee->listFiles(ROOT_DIR . self::TEST_FILE);
    }

    public function testListFiles_throwsWhenPathNotAllowed(): void
    {
        $this->expectException(FileAccessException::class);
        $this->testee->listFiles(self::FORBIDDEN_PATH);
    }

    public function testListFiles_throwsWhenPathDoesNotExist(): void
    {
        $this->expectException(FileAccessException::class);
        $this->testee->listFiles(ROOT_DIR . '/nonexistent-dir-12345');
    }

    public function testListFiles_returnsFiles(): void
    {
        file_put_contents(ROOT_DIR . self::TEST_DIR . '/a.txt', 'a');
        file_put_contents(ROOT_DIR . self::TEST_DIR . '/b.txt', 'b');

        $result = $this->testee->listFiles(ROOT_DIR . self::TEST_DIR);

        self::assertSame(['a.txt', 'b.txt', 'test.txt'], $result);
    }

    public function testListFiles_skipsBrokenSymlinks(): void
    {
        $symlinkPath = ROOT_DIR . self::TEST_DIR . '/broken.link';
        symlink('/nonexistent-target-12345', $symlinkPath);

        file_put_contents(ROOT_DIR . self::TEST_DIR . '/real.txt', 'real');

        $result = $this->testee->listFiles(ROOT_DIR . self::TEST_DIR);

        self::assertSame(['real.txt', 'test.txt'], $result);
    }

    public function testFileExists_throwsWhenResolvedPathNotAllowed(): void
    {
        $symlinkPath = ROOT_DIR . self::TEST_DIR . '/escape.link';
        symlink(self::FORBIDDEN_PATH, $symlinkPath);

        $this->expectException(FileAccessException::class);
        $this->testee->fileExists($symlinkPath);
    }
}
