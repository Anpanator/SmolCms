<?php

declare(strict_types=1);

namespace SmolCms\Test\Integration\Data\Business\Routing;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use SmolCms\Data\Business\Routing\Route;
use SmolCms\Data\Constant\HttpMethod;

class RouteTest extends TestCase
{
    public function testGetHandler_handlerPropertyNullReturnsCorrectDefault(): void
    {
        $defaultHandlerNames = $this->getDefaultHandlerNames();

        foreach ($defaultHandlerNames as $method => $expectedHandlerName) {
            $route = new Route('/', $method, 'SomeController');
            $result = $route->getHandler();

            self::assertSame($expectedHandlerName, $result);
        }
    }

    public function testGetHandler_handlerPropertySetReturnsItsValue(): void
    {
        $expectedHandler = 'specialHandler';
        $route = new Route('/', 'GET', 'SomeController', $expectedHandler);
        $result = $route->getHandler();

        self::assertSame($expectedHandler, $result);
    }

    private function getDefaultHandlerNames(): array
    {
        $reflector = new ReflectionClass(HttpMethod::class);
        $constants = $reflector->getConstants();
        return array_map(fn(string $val) => strtolower($val) . 'Action', $constants);
    }
}
