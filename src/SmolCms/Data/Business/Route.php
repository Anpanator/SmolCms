<?php

declare(strict_types=1);

namespace SmolCms\Data\Business;


use SmolCms\Data\Constant\HttpMethod;

class Route
{
    public function __construct(
        private string $path,
        private HttpMethod $method,
        private string $controller,
        private ?string $handler = null,
    ) {
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function getMethod(): HttpMethod
    {
        return $this->method;
    }

    public function getController(): string
    {
        return $this->controller;
    }

    /**
     * @return string
     */
    public function getHandler(): string
    {
        return $this->handler ?? strtolower($this->method->value) . 'Action';
    }
}