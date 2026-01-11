<?php

declare(strict_types=1);

namespace SmolCms\Data\Response;


use SmolCms\Data\Constant\HttpStatus;

class Response
{

    /**
     * Response constructor.
     */
    public function __construct(
        protected HttpStatus $status = HttpStatus::OK,
        protected ?string    $content = null,
        protected array      $headers = [],
    )
    {
    }

    public function getStatus(): HttpStatus
    {
        return $this->status;
    }

    public function setStatus(HttpStatus $status): void
    {
        $this->status = $status;
    }

    public function getContent(): ?string
    {
        return $this->content;
    }

    public function setContent(?string $content): void
    {
        $this->content = $content;
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function setHeaders(array $headers): void
    {
        $this->headers = $headers;
    }

    public function addHeader(string $name, string $value): void
    {
        $this->headers[$name] = $value;
    }
}