<?php
declare(strict_types=1);

namespace SmolCms\Data\Constant;

enum HttpHeader: string
{
    case CONTENT_TYPE = 'Content-Type';
    case CONTENT_DISPOSITION = 'Content-Disposition';
    case X_ACCEL_REDIRECT = 'X-Accel-Redirect';
}
