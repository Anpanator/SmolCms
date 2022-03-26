<?php
declare(strict_types=1);

namespace SmolCms\TestUtils\Helper;

use PHPUnit\Framework\Constraint\Callback;
use function PHPUnit\Framework\callback;

class Capture
{
    public static function arg(mixed &$captureVar): Callback
    {
        return callback(function(mixed $passedArgument) use (&$captureVar): bool {
            $captureVar = $passedArgument;
            return true;
        });
    }
}