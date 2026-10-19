<?php

declare(strict_types=1);

namespace SmolCms\Test\Service\Core;

use SmolCms\Data\Constant\LoggingMode;
use SmolCms\Data\Constant\LogLevel;
use SmolCms\Service\Core\LoggingService;
use SmolCms\TestUtils\SimpleTestCase;

class LoggingServiceTest extends SimpleTestCase
{
    private string $infoLogFilePath;
    private string $errorLogFilePath;
    private LoggingService $loggingService;

    protected function setUp(): void
    {
        parent::setUp();
        $testId = bin2hex(random_bytes(8));
        $this->infoLogFilePath = ROOT_DIR . "/private/init/logging-info-{$testId}.log";
        $this->errorLogFilePath = ROOT_DIR . "/private/init/logging-error-{$testId}.log";
        $this->loggingService = new LoggingService($this->infoLogFilePath, $this->errorLogFilePath);
    }

    protected function tearDown(): void
    {
        foreach ([$this->infoLogFilePath, $this->errorLogFilePath] as $logFilePath) {
            if (is_file($logFilePath)) {
                unlink($logFilePath);
            }
        }

        parent::tearDown();
    }

    public function testLog_WritesMessagesToSeverityChannels(): void
    {
        $this->loggingService->log(LogLevel::INFO, 'Informational message');
        $this->loggingService->log(LogLevel::ERROR, 'Error message');

        $infoLog = file_get_contents($this->infoLogFilePath);
        $errorLog = file_get_contents($this->errorLogFilePath);

        self::assertIsString($infoLog);
        self::assertIsString($errorLog);
        self::assertMatchesRegularExpression(
            '/^\[\d{2}-[A-Za-z]{3}-\d{4} \d{2}:\d{2}:\d{2} [^\]]+\] \[INFO\] Informational message$/m',
            $infoLog,
        );
        self::assertMatchesRegularExpression(
            '/^\[\d{2}-[A-Za-z]{3}-\d{4} \d{2}:\d{2}:\d{2} [^\]]+\] \[ERROR\] Error message$/m',
            $errorLog,
        );
    }

    public function testLog_UsesTheSelectedPhpErrorLogMode(): void
    {
        $this->loggingService->log(LogLevel::INFO, 'Configured file mode', LoggingMode::FILE);

        $infoLog = file_get_contents($this->infoLogFilePath);
        self::assertIsString($infoLog);
        self::assertStringContainsString('Configured file mode', $infoLog);
    }
}
