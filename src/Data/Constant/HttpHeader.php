<?php
declare(strict_types=1);

namespace SmolCms\Data\Constant;

enum HttpHeader: string
{
    case CONTENT_TYPE = 'Content-Type';
    case CONTENT_DISPOSITION = 'Content-Disposition';
    case AUTHORIZATION = 'Authorization';
    case X_ACCEL_REDIRECT = 'X-Accel-Redirect';
    case ORIGIN = 'Origin';
    case SEC_FETCH_SITE = 'Sec-Fetch-Site';
    case CROSS_ORIGIN_RESOURCE_POLICY = 'Cross-Origin-Resource-Policy';
}
