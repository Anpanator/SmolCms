<?php

declare(strict_types=1);

namespace SmolCms\Config;


use SmolCms\Controller\BaseController;
use SmolCms\Data\Business\Route;
use SmolCms\Data\Constant\HttpMethod;

class RoutingConfiguration
{
    /** @var array<string, Route> */
    private array $routes;

    /**
     * RoutingConfiguration constructor.
     */
    public function __construct() {
        $this->routes = [
            'RouteName' => new Route(
                path: '/example',
                method: HttpMethod::GET,
                controller: BaseController::class
            )
        ];
    }

    /**
     * @return array<string, Route>
     */
    public function getRoutes(): array
    {
        return $this->routes;
    }
}