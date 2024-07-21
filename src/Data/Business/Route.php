<?php

declare(strict_types=1);

namespace SmolCms\Data\Business;


use SmolCms\Data\Constant\HttpMethod;

class Route
{
    public function __construct(
        public readonly string $path,
        public readonly HttpMethod $method,
        public readonly string $controller,
        public readonly ?string $handler = null,
    ) {
    }

    public function getHandlerOrDefault(): string
    {
        return $this->handler ?? strtolower($this->method->value) . 'Action';
    }
}