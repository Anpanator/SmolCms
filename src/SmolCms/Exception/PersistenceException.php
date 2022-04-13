<?php
declare(strict_types=1);

namespace SmolCms\Exception;

use RuntimeException;
use Throwable;

class PersistenceException extends RuntimeException
{

    public function __construct(string $message, ?Throwable $originalError = null)
    {
        parent::__construct(message: $message, previous: $originalError);
    }
}