<?php

declare(strict_types=1);

namespace SmolCms\Config;


use SmolCms\Controller\AuthController;
use SmolCms\Controller\ImageUploadController;
use SmolCms\Controller\IndexController;
use SmolCms\Controller\LoginController;
use SmolCms\Controller\RegisterController;
use SmolCms\Controller\UserSettingController;
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
                method: HttpMethod::GET,
                controller: LoginController::class,
            ),
            RouteEnum::REGISTER->name => new Route(
                path: RouteEnum::REGISTER->value,
                method: HttpMethod::GET,
                controller: RegisterController::class,
            ),
            'RegisterPostRoute' => new Route(
                path: RouteEnum::REGISTER->value,
                method: HttpMethod::POST,
                controller: RegisterController::class,
            ),
            'LoginPostRoute' => new Route(
                path: RouteEnum::LOGIN->value,
                method: HttpMethod::POST,
                controller: AuthController::class
            ),
            RouteEnum::SETTINGS->name => new Route(
                path: RouteEnum::SETTINGS->value,
                method: HttpMethod::GET,
                controller: UserSettingController::class,
            ),
            'SettingsPostRoute' => new Route(
                path: RouteEnum::SETTINGS->value,
                method: HttpMethod::POST,
                controller: UserSettingController::class,
            ),
            RouteEnum::LOGOUT->name => new Route(
                path: RouteEnum::LOGOUT->value,
                method: HttpMethod::POST,
                controller: AuthController::class,
                handler: 'logoutAction'
            ),
            RouteEnum::UPLOAD_IMAGE->name => new Route(
                path: RouteEnum::UPLOAD_IMAGE->value,
                method: HttpMethod::GET,
                controller: ImageUploadController::class,
            ),
            'ImageUploadPostRoute' => new Route(
                path: RouteEnum::UPLOAD_IMAGE->value,
                method: HttpMethod::POST,
                controller: ImageUploadController::class,
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
