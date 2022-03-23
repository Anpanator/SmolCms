<?php

declare(strict_types=1);

namespace SmolCms\Data\Request;


use SmolCms\Data\Business\Url;
use SmolCms\Data\Constant\HttpMethod;

class Request
{

    public function __construct(
        private Url        $url,
        private HttpMethod $method,
        private array      $headers = [],
        private ?string    $rawBody = null,
        private ?array     $postParams = null,
        private ?array     $getParams = null,
    )
    {
    }

    public function getUrl(): Url
    {
        return $this->url;
    }

    public function getMethod(): HttpMethod
    {
        return $this->method;
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function getRawBody(): ?string
    {
        return $this->rawBody;
    }

    public function getQueryParam(string $key): ?string
    {
        return $this->getParams[$key] ?? null;
    }

    public function getPostParam(string $key): ?string
    {
        return $this->getParams[$key] ?? null;
    }
}