<?php

declare(strict_types=1);

namespace SmolCms\Data\Business\Routing;


class Route
{
    public function __construct(
        private string $path,
        private string $method,
        private string $controller,
        private array $defaults = []
    ) {
    }
}