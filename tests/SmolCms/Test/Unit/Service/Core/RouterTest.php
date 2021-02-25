<?php

declare(strict_types=1);

namespace SmolCms\Test\Unit\Service\Core;

use PHPUnit\Framework\MockObject\MockObject;
use SmolCms\Config\RoutingConfiguration;
use SmolCms\Controller\BaseController;
use SmolCms\Data\Business\Route;
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
        $url = 'https://example.com/example';
        $path = '/example';
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

    protected function setUp(): void
    {
        parent::setUp();
        $this->router = new Router($this->routingConfiguration);
    }
}
