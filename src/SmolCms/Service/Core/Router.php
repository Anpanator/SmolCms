<?php

declare(strict_types=1);

namespace SmolCms\Service\Core;


use SmolCms\Config\RoutingConfiguration;
use SmolCms\Data\Business\Route;

class Router
{

    /**
     * Router constructor.
     */
    public function __construct(private RoutingConfiguration $routingConfiguration)
    {
    }

    /**
     * @param string $url
     * @param string $method
     * @return Route|null
     */
    public function getRouteByUrlAndMethod(string $url, string $method): ?Route
    {
        $urlParts = parse_url($url);
        if (!is_array($urlParts)) {
            return null;
        }
        $path = $urlParts['path'] ?? null;
        if (!$path) {
            return null;
        }

        foreach ($this->routingConfiguration->getRoutes() as $route) {
            if ($route->getPath() === $path && $route->getMethod() === $method) {
                return $route;
            }
        }

        return null;
    }
}