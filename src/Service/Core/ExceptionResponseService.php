<?php

declare(strict_types=1);

namespace SmolCms\Service\Core;


use SmolCms\Data\Constant\HttpStatus;
use SmolCms\Data\Constant\LogLevel;
use SmolCms\Data\Response\Response;
use SmolCms\Exception\BadRequestException;
use SmolCms\Exception\DuplicateImageException;
use Throwable;

class ExceptionResponseService
{
    public function __construct(private LoggingService $loggingService)
    {
    }

    public function createResponseFromException(Throwable $e): Response
    {
        $status = match (true) {
            $e instanceof BadRequestException => HttpStatus::BAD_REQUEST,
            $e instanceof DuplicateImageException => HttpStatus::CONFLICT,
            default => HttpStatus::INTERNAL_SERVER_ERROR,
        };

        $level = match ($status) {
            HttpStatus::INTERNAL_SERVER_ERROR => LogLevel::ERROR,
            default => LogLevel::INFO,
        };
        $this->loggingService->log($level, $e->getMessage());

        return new Response(
            status: $status,
            content: $this->getStackTraceIfDisplayErrorsEnabled($e),
        );
    }

    private function getStackTraceIfDisplayErrorsEnabled(Throwable $e): ?string
    {
        $displayErrors = ini_get('display_errors');
        if ($displayErrors === false) {
            return null;
        }

        $isEnabled = filter_var($displayErrors, FILTER_VALIDATE_BOOLEAN)
            || in_array(strtolower($displayErrors), ['stdout', 'stderr'], true);

        return $isEnabled ? $e->getTraceAsString() : null;
    }
}
