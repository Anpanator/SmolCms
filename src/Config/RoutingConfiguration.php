<?php

declare(strict_types=1);

namespace SmolCms\Config;


use SmolCms\Controller\IndexController;
use SmolCms\Controller\LoginController;
use SmolCms\Data\Business\Route;
use SmolCms\Data\Constant\HttpMethod;
use SmolCms\Data\Constant\RouteEnum;

class RoutingConfiguration
{
    /** @var array<string, Route> */
    private array $routes;

    /**
     * RoutingConfiguration constructor.
     */
    public function __construct()
    {
        $this->routes = [
            RouteEnum::START_PAGE->name => new Route(
                path: RouteEnum::START_PAGE->value,
                method: HttpMethod::GET,
                controller: IndexController::class
            ),
            RouteEnum::LOGIN->name => new Route(
                path: RouteEnum::LOGIN->value,
                method: HttpMethod::POST,
                controller: LoginController::class
            ),
            'IndexPostRoute' => new Route(
                path: RouteEnum::START_PAGE->value,
                method: HttpMethod::POST,
                controller: IndexController::class
            ),
            'IndexPathParamRoute' => new Route(
                path: '/{coolParam}/{fancyParam}',
                method: HttpMethod::POST,
                controller: IndexController::class,
                handler: 'pathParamAction'
            ),
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