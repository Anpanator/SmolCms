<?php

declare(strict_types=1);

namespace SmolCms\Data\Constant;


enum HttpStatus: int
{
    case OK = 200;
    case CREATED = 201;
    case ACCEPTED = 202;
    case NO_CONTENT = 204;

    case NOT_FOUND = 404;
}