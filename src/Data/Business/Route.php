<?php

declare(strict_types=1);

namespace SmolCms\Data\Business;


use SmolCms\Data\Constant\HttpMethod;

readonly class Route
{
    public function __construct(
        public string     $path,
        public HttpMethod $method,
        public string     $controller,
        public ?string    $handler = null,
    ) {
    }

    public function getHandlerOrDefault(): string
    {
        return $this->handler ?? strtolower($this->method->value) . 'Action';
    }
}