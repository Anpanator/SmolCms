<?php

declare(strict_types=1);

namespace SmolCms\Service\Core;


use SmolCms\Config\RoutingConfiguration;
use SmolCms\Data\Business\Route;
use SmolCms\Data\Business\Url;
use SmolCms\Data\Constant\HttpMethod;

class Router
{
    private const TRAILING_URL_CHARS_TO_REMOVE = ' /';

    /**
     * Router constructor.
     * @param RoutingConfiguration $routingConfiguration
     */
    public function __construct(private RoutingConfiguration $routingConfiguration)
    {
    }

    public function getRouteByUrlAndMethod(Url $url, HttpMethod $method): ?Route
    {
        if (!$url->getPath()) {
            return null;
        }
        $actualUrlParts = explode('/', rtrim($url->getPath(), self::TRAILING_URL_CHARS_TO_REMOVE));
        if (!$actualUrlParts) {
            return null;
        }
        $actualUrlPartsCnt = count($actualUrlParts);
        /** @var Route $route */
        foreach ($this->routingConfiguration->getRoutes() as $route) {
            if ($route->method !== $method) {
                continue;
            }

            $routeParts = explode('/', rtrim($route->path, self::TRAILING_URL_CHARS_TO_REMOVE));
            if (!$routeParts || $actualUrlPartsCnt !== count($routeParts)) {
                continue;
            }

            if ($this->urlPartsMatch($actualUrlParts, $routeParts)) {
                return $route;
            }
        }

        return null;
    }

    /**
     * Array indices MUST be numeric and in the same order
     *
     * @param array $actualUrlParts
     * @param array $routeParts
     * @return bool
     */
    private function urlPartsMatch(array $actualUrlParts, array $routeParts): bool
    {
        foreach ($routeParts as $i => $routePart) {
            if ($routePart === '' || $routePart[0] === '{' || $routePart === $actualUrlParts[$i]) {
                continue;
            }
            return false;
        }
        // All parts were either empty, a placeholder or matched exactly, so this is the correct route
        return true;
    }
}