<?php

declare(strict_types=1);

namespace SmolCms\Data\Constant;

/**
 * Destinations supported by PHP's error_log() function.
 */
enum LoggingMode: int
{
    case SYSTEM = 0;
    case FILE = 3;
    case SAPI = 4;
}
