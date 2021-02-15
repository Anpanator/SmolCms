<?php

declare(strict_types=1);

namespace SmolCms\Config;


use SmolCms\Controller\Interfaces\BaseController;
use SmolCms\Data\Business\Routing\Route;
use SmolCms\Data\Constant\HttpMethod;

class RoutingConfiguration
{
    /**
     * @return Route[]
     */
    public function getRoutes(): array
    {
        return [
            new Route(
                path: '/example',
                method: HttpMethod::GET,
                controller: BaseController::class
            )
        ];
    }
}