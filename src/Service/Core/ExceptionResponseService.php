<?php

declare(strict_types=1);

namespace SmolCms\Service\Core;


use SmolCms\Data\Constant\HttpStatus;
use SmolCms\Data\Response\Response;
use SmolCms\Exception\BadRequestException;
use Throwable;

class ExceptionResponseService
{
    public function createResponseFromException(Throwable $e): Response
    {
        $status = match (true) {
            $e instanceof BadRequestException => HttpStatus::BAD_REQUEST,
            default => HttpStatus::INTERNAL_SERVER_ERROR,
        };

        return new Response(status: $status);
    }
}
