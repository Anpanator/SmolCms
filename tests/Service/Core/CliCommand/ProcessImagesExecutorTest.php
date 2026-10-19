<?php

declare(strict_types=1);

namespace SmolCms\Test\Service\Core\CliCommand;

use PHPUnit\Framework\MockObject\MockObject;
use RuntimeException;
use SmolCms\Data\Constant\CliCommandFlag;
use SmolCms\Data\Constant\LogLevel;
use SmolCms\Service\Core\CliCommand\ProcessImagesExecutor;
use SmolCms\Service\Core\LoggingService;
use SmolCms\Service\Image\ImageProcessingService;
use SmolCms\TestUtils\Attributes\Stub;
use SmolCms\TestUtils\SimpleTestCase;

class ProcessImagesExecutorTest extends SimpleTestCase
{
    private const string LOCK_DIR = '/private/upload/test-process-images';

    private string $lockFilePath;

    #[Stub(ImageProcessingService::class)]
    private ImageProcessingService|MockObject $imageProcessingService;

    #[Stub(LoggingService::class)]
    private LoggingService|MockObject $loggingService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->lockFilePath = ROOT_DIR . self::LOCK_DIR . '/processing-' . bin2hex(random_bytes(8)) . '.lock';
        if (!is_dir(ROOT_DIR . self::LOCK_DIR) && !mkdir(ROOT_DIR . self::LOCK_DIR, 0777, true)) {
            throw new RuntimeException('Could not create lock directory for tests.');
        }
    }

    protected function tearDown(): void
    {
        self::rmDirRecursive(ROOT_DIR . self::LOCK_DIR);
        parent::tearDown();
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

    private function createTestee(ImageProcessingService|MockObject $imageProcessingService): ProcessImagesExecutor
    {
        return new ProcessImagesExecutor($imageProcessingService, $this->lockFilePath, $this->loggingService);
    }

    public function testHandles_returnsProcessImages(): void
    {
        self::assertSame(CliCommandFlag::PROCESS_IMAGES, $this->createTestee($this->createStub(ImageProcessingService::class))->handles());
    }

    public function testExecute_withoutBatchSizeArg_usesDefault(): void
    {
        $imageProcessingService = $this->createMock(ImageProcessingService::class);
        $imageProcessingService
            ->expects($this->once())
            ->method('processBatch')
            ->with(100)
            ->willReturn(0);

        $this->expectOutputString("Processed 0 image(s).\n");
        $this->createTestee($imageProcessingService)->execute([], true);
    }

    public function testExecute_withValidBatchSize_passesItToTheService(): void
    {
        $imageProcessingService = $this->createMock(ImageProcessingService::class);
        $imageProcessingService
            ->expects($this->once())
            ->method('processBatch')
            ->with(25)
            ->willReturn(3);

        $this->expectOutputString("Processed 3 image(s).\n");
        $this->createTestee($imageProcessingService)->execute(['25'], true);
    }

    public function testExecute_lockIsReleasedAfterRun(): void
    {
        $imageProcessingService = $this->createStub(ImageProcessingService::class);
        $imageProcessingService
            ->method('processBatch')
            ->willReturn(0);

        ob_start();
        $this->createTestee($imageProcessingService)->execute([], true);
        ob_end_clean();
        ob_start();
        $this->createTestee($imageProcessingService)->execute([], true);
        $output = ob_get_clean();

        // If the lock had leaked, the second run would have been a clean no-op instead.
        self::assertIsString($output);
        self::assertStringContainsString('Processed 0 image(s).', $output);
        self::assertStringNotContainsString('Another worker is already processing images.', $output);
        self::assertFileDoesNotExist($this->lockFilePath);
    }

    public function testExecute_processingFailureIsLoggedAndLockFileIsRemoved(): void
    {
        $exception = new RuntimeException('processing failed');
        $this->imageProcessingService
            ->method('processBatch')
            ->willThrowException($exception);
        $loggedErrors = [];
        $this->loggingService
            ->method('log')
            ->willReturnCallback(static function (LogLevel $level, string $message) use (&$loggedErrors): void {
                $loggedErrors[] = [$level, $message];
            });

        try {
            $this->createTestee($this->imageProcessingService)->execute([], true);
            self::fail('Expected the processing exception to be rethrown.');
        } catch (RuntimeException $thrown) {
            self::assertSame($exception, $thrown);
        }

        self::assertFileDoesNotExist($this->lockFilePath);
        self::assertSame(
            [[LogLevel::ERROR, 'Image processing worker failed with RuntimeException: processing failed']],
            $loggedErrors
        );
    }

    public function testExecute_heldLock_isCleanNoOp(): void
    {
        $lock = fopen($this->lockFilePath, 'c');
        self::assertNotFalse($lock);
        self::assertTrue(flock($lock, LOCK_EX | LOCK_NB));
        try {
            $imageProcessingService = $this->createMock(ImageProcessingService::class);
            $imageProcessingService
                ->expects($this->never())
                ->method('processBatch')
                ->seal();

            $this->expectOutputString("Another worker is already processing images.\n");
            $this->createTestee($imageProcessingService)->execute(['100'], true);
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    public function testExecute_nonNumericBatchSize_printsUsageWithoutProcessing(): void
    {
        $imageProcessingService = $this->createMock(ImageProcessingService::class);
        $imageProcessingService
            ->expects($this->never())
            ->method('processBatch')
            ->seal();

        $this->expectOutputString(
            "Usage: --process-images [batch-size]\n"
            . "Batch size must be a positive integer; default is 100.\n"
        );
        $this->createTestee($imageProcessingService)->execute(['abc'], true);
    }

    public function testExecute_zeroBatchSize_printsUsageWithoutProcessing(): void
    {
        $imageProcessingService = $this->createMock(ImageProcessingService::class);
        $imageProcessingService
            ->expects($this->never())
            ->method('processBatch')
            ->seal();

        $this->expectOutputString(
            "Usage: --process-images [batch-size]\n"
            . "Batch size must be a positive integer; default is 100.\n"
        );
        $this->createTestee($imageProcessingService)->execute(['0'], true);
    }

    public function testExecute_negativeBatchSize_printsUsageWithoutProcessing(): void
    {
        $imageProcessingService = $this->createMock(ImageProcessingService::class);
        $imageProcessingService
            ->expects($this->never())
            ->method('processBatch')
            ->seal();

        $this->expectOutputString(
            "Usage: --process-images [batch-size]\n"
            . "Batch size must be a positive integer; default is 100.\n"
        );
        $this->createTestee($imageProcessingService)->execute(['-5'], true);
    }

    public function testExecute_fractionalBatchSize_printsUsageWithoutProcessing(): void
    {
        $imageProcessingService = $this->createMock(ImageProcessingService::class);
        $imageProcessingService
            ->expects($this->never())
            ->method('processBatch')
            ->seal();

        $this->expectOutputString(
            "Usage: --process-images [batch-size]\n"
            . "Batch size must be a positive integer; default is 100.\n"
        );
        $this->createTestee($imageProcessingService)->execute(['1.5'], true);
    }

    public function testHelptext_mentionsFlagAndUsage(): void
    {
        $executor = $this->createTestee($this->createStub(ImageProcessingService::class));
        self::assertStringContainsString('--process-images', $executor->helptext());
    }
}
