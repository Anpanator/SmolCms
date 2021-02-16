<?php

declare(strict_types=1);

namespace SmolCms\Config;


use SmolCms\Controller\Interfaces\BaseController;
use SmolCms\Data\Business\Routing\Route;
use SmolCms\Data\Constant\HttpMethod;

class RoutingConfiguration
{
    /**
     * RoutingConfiguration constructor.
     * @param Route[] $routes
     */
    public function __construct(
        private array $routes = [
            new Route(
                path: '/example',
                method: HttpMethod::GET,
                controller: BaseController::class
            )
        ]
    )
    {}

    /**
     * @return Route[]
     */
    public function getRoutes(): array
    {
        return $this->routes;
    }
}