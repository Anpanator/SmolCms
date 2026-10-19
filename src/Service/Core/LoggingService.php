<?php

declare(strict_types=1);

namespace SmolCms\Service\Core;

use SmolCms\Data\Constant\LoggingMode;
use SmolCms\Data\Constant\LogLevel;

class LoggingService
{
    public function __construct(
        private string $infoLogFilePath,
        private string $errorLogFilePath,
    )
    {
    }

    public function log(
        LogLevel    $level,
        string      $message,
        LoggingMode $mode = LoggingMode::FILE,
    ): void
    {
        $formattedMessage = sprintf(
            '[%s] [%s] %s',
            date('d-M-Y H:i:s T'),
            strtoupper($level->value),
            $message,
        );

        if ($mode === LoggingMode::FILE) {
            error_log($formattedMessage, $mode->value, $this->getLogFilePath($level));
            return;
        }

        error_log($formattedMessage, $mode->value);
    }

    private function getLogFilePath(LogLevel $level): string
    {
        return match ($level) {
            LogLevel::WARNING,
            LogLevel::ERROR,
            LogLevel::CRITICAL,
            LogLevel::ALERT,
            LogLevel::EMERGENCY => $this->errorLogFilePath,
            default => $this->infoLogFilePath,
        };
    }
}
