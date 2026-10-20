<?php

declare(strict_types=1);

namespace SmolCms\Service\Core;

use Closure;
use SmolCms\Data\Constant\LoggingMode;
use SmolCms\Data\Constant\LogLevel;

class LoggingService
{
    private ?Closure $errorLogEmitter = null;

    public function __construct(
        private string $infoLogFilePath,
        private string $errorLogFilePath,
    )
    {
    }

    /** Injects the PHP error_log backend when destination side effects need observation. */
    public function setErrorLogEmitter(Closure $errorLogEmitter): void
    {
        $this->errorLogEmitter = $errorLogEmitter;
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
            $this->emitErrorLog($formattedMessage, $mode, $this->getLogFilePath($level));
            return;
        }

        $this->emitErrorLog($formattedMessage, $mode);
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

    private function emitErrorLog(string $message, LoggingMode $mode, ?string $destination = null): void
    {
        if ($this->errorLogEmitter !== null) {
            ($this->errorLogEmitter)($message, $mode->value, $destination);
            return;
        }

        if ($destination !== null) {
            error_log($message, $mode->value, $destination);
            return;
        }

        error_log($message, $mode->value);
    }
}
