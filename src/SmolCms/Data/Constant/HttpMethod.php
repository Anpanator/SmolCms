<?php

declare(strict_types=1);

namespace SmolCms\Data\Constant;


abstract class HttpMethod
{
    public const GET = 'GET';
    public const POST = 'POST';
    public const PUT = 'PUT';
    public const DELETE = 'DELETE';
    public const OPTIONS = 'OPTIONS';
}