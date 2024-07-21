<?php
declare(strict_types=1);

namespace SmolCms\Test\Unit\Service\Url;

use SmolCms\Service\Url\PathParamMappingService;
use SmolCms\TestUtils\SimpleTestCase;

class PathParamMappingServiceTest extends SimpleTestCase
{
    private PathParamMappingService $pathParamMappingService;

    public function testGetPathParamsByUrlPathAndRoutePattern_success()
    {
        $param1 = 'param1value';
        $param1Name = 'param1Name';
        $param2 = 'param2value';
        $param2Name = 'param2Name';
        $path = "/actual/path/$param1/and/$param2";
        $routePattern = '/actual/path/{' . $param1Name . '}/and/{' . $param2Name . '}';

        $result = $this->pathParamMappingService->getPathParamsByUrlPathAndRoutePattern($path, $routePattern);
        self::assertSame([$param1Name => $param1, $param2Name => $param2], $result);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->pathParamMappingService = new PathParamMappingService();
    }
}
