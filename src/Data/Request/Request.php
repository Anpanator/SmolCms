<?php

declare(strict_types=1);

namespace SmolCms\Data\Request;


use SmolCms\Data\Business\Url;
use SmolCms\Data\Constant\HttpMethod;

readonly class Request
{

    public function __construct(
        public Url        $url,
        public HttpMethod $method,
        public array      $headers = [],
        public ?string    $rawBody = null,
        public ?array     $postParams = null,
        public ?array     $getParams = null,
    )
    {
    }
}