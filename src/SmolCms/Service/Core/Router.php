<?php

declare(strict_types=1);

namespace SmolCms\Service\Core;


use SmolCms\Config\RoutingConfiguration;
use SmolCms\Data\Business\Route;
use SmolCms\Data\Business\Url;

class Router
{

    /**
     * Router constructor.
     * @param RoutingConfiguration $routingConfiguration
     */
    public function __construct(private RoutingConfiguration $routingConfiguration)
    {
    }

    /**
     * @param Url $url
     * @param string $method
     * @return Route|null
     */
    public function getRouteByUrlAndMethod(Url $url, string $method): ?Route
    {
        if (!$url->getPath()) {
            return null;
        }

        foreach ($this->routingConfiguration->getRoutes() as $route) {
            if ($route->getPath() === $url->getPath() && $route->getMethod() === $method) {
                return $route;
            }
        }

        return null;
    }
}