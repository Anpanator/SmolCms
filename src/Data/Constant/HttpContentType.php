<?php

declare(strict_types=1);

namespace SmolCms\Data\Constant;

enum HttpContentType: string
{
    case JSON = 'application/json';
    case HTML_UTF_8 = 'text/html; charset=UTF-8';
    case FORM_URL_ENCODED = 'application/x-www-form-urlencoded';
}
