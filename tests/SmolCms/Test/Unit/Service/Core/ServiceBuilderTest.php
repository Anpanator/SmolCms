<?php

declare(strict_types=1);

namespace SmolCms\Test\Unit\Service\Core;

use PHPUnit\Framework\TestCase;
use SmolCms\Service\Core\ServiceBuilder;

class ServiceBuilderTest extends TestCase
{
    private ServiceBuilder $serviceBuilder;

    public function testBuild_success()
    {
        $result = $this->serviceBuilder->build(TestClass::class);
        self::assertNotNull($result);
        self::assertInstanceOf(TestClass::class, $result);
    }

    protected function setUp(): void
    {
        $this->serviceBuilder = new ServiceBuilder();
    }
}


final class TestClass
{
    public function __construct(private AnotherTestClass $anotherTestClass)
    {
    }
}

final class AnotherTestClass
{
}