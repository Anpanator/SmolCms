<?php

declare(strict_types=1);

namespace SmolCms\Test\Integration\Data\Business\Routing;

use PHPUnit\Framework\TestCase;
use SmolCms\Data\Business\Route;
use SmolCms\Data\Constant\HttpMethod;

class RouteTest extends TestCase
{
    public function testGetHandler_handlerPropertyNullReturnsCorrectDefaultFromHttpMethod(): void
    {
        $defaultHandlerNames = $this->getDefaultHandlerNames();

        foreach ($defaultHandlerNames as $method => $expectedHandlerName) {
            $route = new Route('/', HttpMethod::from($method), 'SomeController');
            $result = $route->getHandlerOrDefault();

            self::assertSame($expectedHandlerName, $result);
        }
    }

    public function testGetHandler_handlerPropertySetReturnsItsValueOverDefault(): void
    {
        $expectedHandler = 'specialHandler';
        $route = new Route('/', HttpMethod::GET, 'SomeController', $expectedHandler);
        $result = $route->getHandlerOrDefault();

        self::assertSame($expectedHandler, $result);
    }

    /**
     * @return string[]
     *
     * Returns all default handler names, i.e. every HTTP Method defined in the HttpMethod class.
     */
    private function getDefaultHandlerNames(): array
    {
        $handlerMap = [];
        foreach (HttpMethod::cases() as $httpMethod) {
            $handlerMap[$httpMethod->value] = strtolower($httpMethod->value) . 'Action';
        }
        return $handlerMap;
    }
}
