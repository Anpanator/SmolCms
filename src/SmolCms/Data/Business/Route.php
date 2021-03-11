<?php

declare(strict_types=1);

namespace SmolCms\Data\Business;


class Route
{
    public function __construct(
        private string $path,
        private string $method,
        private string $controller,
        private ?string $handler = null,
    ) {
    }

    /**
     * @return string
     */
    public function getPath(): string
    {
        return $this->path;
    }

    /**
     * @return string
     */
    public function getMethod(): string
    {
        return $this->method;
    }

    /**
     * @return string
     */
    public function getController(): string
    {
        return $this->controller;
    }

    /**
     * @return string
     */
    public function getHandler(): string
    {
        return $this->handler ?? strtolower($this->method) . 'Action';
    }
}