<?php

declare(strict_types=1);

namespace SmolCms\Data\Request;


use SmolCms\Data\Business\Url;
use SmolCms\Data\Constant\HttpMethod;

class Request
{

    public function __construct(
        public readonly Url        $url,
        public readonly HttpMethod $method,
        public readonly array      $headers = [],
        public readonly ?string    $rawBody = null,
        public readonly ?array     $postParams = null,
        public readonly ?array     $getParams = null,
    )
    {
    }
}