<?php

declare(strict_types=1);

namespace SmolCms\Test\Service\Core;

use PHPUnit\Framework\MockObject\MockObject;
use RuntimeException;
use SmolCms\Data\Constant\HttpStatus;
use SmolCms\Data\Constant\LogLevel;
use SmolCms\Exception\BadRequestException;
use SmolCms\Service\Core\ExceptionResponseService;
use SmolCms\Service\Core\LoggingService;
use SmolCms\TestUtils\Attributes\Mock;
use SmolCms\TestUtils\SimpleTestCase;

class ExceptionResponseServiceTest extends SimpleTestCase
{
    #[Mock(LoggingService::class)]
    private LoggingService|MockObject $loggingService;

    private ExceptionResponseService $exceptionResponseService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->exceptionResponseService = new ExceptionResponseService($this->loggingService);
    }

    public function testCreateResponseFromException_LogsInternalServerErrorAtErrorLevel(): void
    {
        $exception = new RuntimeException('Unexpected failure');
        $this->loggingService
            ->expects(self::once())
            ->method('log')
            ->with(LogLevel::ERROR, 'Unexpected failure')
            ->seal();

        $response = $this->exceptionResponseService->createResponseFromException($exception);

        self::assertSame(HttpStatus::INTERNAL_SERVER_ERROR, $response->getStatus());
    }

    public function testCreateResponseFromException_LogsBadRequestAtInfoLevel(): void
    {
        $exception = new BadRequestException('Invalid request');
        $this->loggingService
            ->expects(self::once())
            ->method('log')
            ->with(LogLevel::INFO, 'Invalid request')
            ->seal();

        $response = $this->exceptionResponseService->createResponseFromException($exception);

        self::assertSame(HttpStatus::BAD_REQUEST, $response->getStatus());
    }

    public function testCreateResponseFromException_ReturnsStackTraceWhenDisplayErrorsIsEnabled(): void
    {
        $previousDisplayErrors = ini_set('display_errors', '1');
        $exception = new RuntimeException('Unexpected failure');
        $this->loggingService
            ->expects(self::once())
            ->method('log')
            ->with(LogLevel::ERROR, 'Unexpected failure')
            ->seal();

        try {
            $response = $this->exceptionResponseService->createResponseFromException($exception);
        } finally {
            if ($previousDisplayErrors !== false) {
                ini_set('display_errors', $previousDisplayErrors);
            }
        }

        self::assertSame($exception->getTraceAsString(), $response->getContent());
    }

    public function testCreateResponseFromException_DoesNotReturnStackTraceWhenDisplayErrorsIsDisabled(): void
    {
        $previousDisplayErrors = ini_set('display_errors', '0');
        $exception = new RuntimeException('Unexpected failure');
        $this->loggingService
            ->expects(self::once())
            ->method('log')
            ->with(LogLevel::ERROR, 'Unexpected failure')
            ->seal();

        try {
            $response = $this->exceptionResponseService->createResponseFromException($exception);
        } finally {
            if ($previousDisplayErrors !== false) {
                ini_set('display_errors', $previousDisplayErrors);
            }
        }

        self::assertNull($response->getContent());
    }
}
