<?php
declare(strict_types=1);

namespace SmolCms\Data\Constant;

enum HttpHeader: string
{
    case CONTENT_TYPE = 'Content-Type';
    case CONTENT_DISPOSITION = 'Content-Disposition';
    case AUTHORIZATION = 'Authorization';
    case COOKIE = 'Cookie';
    case ALLOW = 'Allow';
    case X_ACCEL_REDIRECT = 'X-Accel-Redirect';
    case ORIGIN = 'Origin';
    case SEC_FETCH_SITE = 'Sec-Fetch-Site';
    case CROSS_ORIGIN_RESOURCE_POLICY = 'Cross-Origin-Resource-Policy';
    case ACCESS_CONTROL_ALLOW_ORIGIN = 'Access-Control-Allow-Origin';
    case ACCESS_CONTROL_ALLOW_METHODS = 'Access-Control-Allow-Methods';
    case ACCESS_CONTROL_ALLOW_HEADERS = 'Access-Control-Allow-Headers';
    case ACCESS_CONTROL_MAX_AGE = 'Access-Control-Max-Age';
}
