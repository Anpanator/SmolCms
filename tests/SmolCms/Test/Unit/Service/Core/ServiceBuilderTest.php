<?php

declare(strict_types=1);

namespace SmolCms\Test\Unit\Service\Core;

use PHPUnit\Framework\TestCase;
use SmolCms\Exception\AutowireException;
use SmolCms\Service\Core\ServiceBuilder;

class ServiceBuilderTest extends TestCase
{
    private ServiceBuilder $serviceBuilder;

    public function testBuild_success()
    {
        $result = $this->serviceBuilder->build(TestClass::class);
        self::assertInstanceOf(TestClass::class, $result);
    }

    public function testBuild_throwsAutowireExceptionWithScalarDependencyAndNoManualConfig()
    {
        $this->expectException(AutowireException::class);
        $this->serviceBuilder->build(TestClassWithScalarDependencies::class);
    }

    public function testBuild_throwsAutowireExceptionWithUntypedDependencyAndNoManualConfig()
    {
        $this->expectException(AutowireException::class);
        $this->serviceBuilder->build(TestClassWithUntypedDependencies::class);
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

final class TestClassWithScalarDependencies
{
    public function __construct(private AnotherTestClass $anotherTestClass, private int $intVal)
    {
    }
}

final class TestClassWithUntypedDependencies
{

    public function __construct(private AnotherTestClass $anotherTestClass, private $untypedValue)
    {
    }
}