<?php

declare(strict_types=1);

namespace SmolCms\Test\Unit\Service\Core;

use PHPUnit\Framework\MockObject\MockObject;
use SmolCms\Config\RoutingConfiguration;
use SmolCms\Controller\BaseController;
use SmolCms\Data\Business\Route;
use SmolCms\Data\Business\Url;
use SmolCms\Data\Constant\HttpMethod;
use SmolCms\Service\Core\Router;
use SmolCms\TestUtils\Attributes\Mock;
use SmolCms\TestUtils\SimpleTestCase;

class RouterTest extends SimpleTestCase
{
    private Router $router;
    #[Mock(RoutingConfiguration::class)]
    private RoutingConfiguration|MockObject $routingConfiguration;

    public function testGetRouteByUrlAndMethod_successSimpleUrl()
    {
        $path = '/example';
        $url = new Url(
            protocol: 'https',
            host: 'example.com',
            path: $path,
        );
        $this->routingConfiguration
            ->method('getRoutes')
            ->willReturn(
                [
                    new Route(path: $path, method: HttpMethod::GET, controller: BaseController::class),
                    $postRoute = new Route(path: $path, method: HttpMethod::POST, controller: BaseController::class),
                    new Route(path: $path, method: HttpMethod::PUT, controller: BaseController::class),
                ]
            );
        $result = $this->router->getRouteByUrlAndMethod($url, HttpMethod::POST);
        self::assertNotNull($result);
        self::assertSame($postRoute, $result);
    }

    public function testGetRouteByUrlAndMethod_successUrlWithPathParams()
    {
        $simplePath = '/example';
        $pathWithParams = '/{coolParam}/{fancyParam}/';

        $url = new Url(
            protocol: 'https',
            host: 'example.com',
            path: '/coolParamValue/fancyParamValue',
        );
        $this->routingConfiguration
            ->method('getRoutes')
            ->willReturn(
                [
                    new Route(path: $simplePath, method: HttpMethod::GET, controller: BaseController::class),
                    new Route(path: $simplePath, method: HttpMethod::POST, controller: BaseController::class),
                    new Route(path: $simplePath, method: HttpMethod::PUT, controller: BaseController::class),
                    $complexRoute = new Route(
                        path: $pathWithParams,
                        method: HttpMethod::POST,
                        controller: BaseController::class,
                        handler: 'pathParamAction'
                    )
                ]
            );
        $result = $this->router->getRouteByUrlAndMethod($url, HttpMethod::POST);
        self::assertNotNull($result);
        self::assertSame($complexRoute, $result);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->router = new Router($this->routingConfiguration);
    }
}
