<?php

declare(strict_types=1);

namespace SmolCms\Data\Request;


use SmolCms\Data\Business\Url;

class Request
{

    /**
     * Request constructor.
     * @param Url $url
     * @param string $method
     * @param array $headers
     * @param string|null $rawBody
     * @param array|null $postParams
     * @param array|null $getParams
     */
    public function __construct(
        private Url $url,
        private string $method,
        private array $headers = [],
        private ?string $rawBody = null,
        private ?array $postParams = null,
        private ?array $getParams = null,
    ) {
    }

    /**
     * @return Url
     */
    public function getUrl(): Url
    {
        return $this->url;
    }

    /**
     * @return string
     */
    public function getMethod(): string
    {
        return $this->method;
    }

    /**
     * @return array
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    /**
     * @return string|null
     */
    public function getRawBody(): ?string
    {
        return $this->rawBody;
    }

    /**
     * @param string $key
     * @return string|null
     */
    public function getQueryParam(string $key): ?string
    {
        return $this->getParams[$key] ?? null;
    }

    /**
     * @param string $key
     * @return string|null
     */
    public function getPostParam(string $key): ?string
    {
        return $this->getParams[$key] ?? null;
    }
}