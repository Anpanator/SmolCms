<?php

declare(strict_types=1);

namespace SmolCms\Test\Unit\Service\Core;

use InvalidArgumentException;
use PHPUnit\Framework\MockObject\MockObject;
use SmolCms\Config\ServiceConfiguration;
use SmolCms\Exception\AutowireException;
use SmolCms\Service\Core\ServiceBuilder;
use SmolCms\TestUtils\Attributes\Mock;
use SmolCms\TestUtils\SimpleTestCase;

class ServiceBuilderTest extends SimpleTestCase
{
    private ServiceBuilder $serviceBuilder;
    #[Mock(ServiceConfiguration::class)]
    private ServiceConfiguration|MockObject $serviceConfiguration;

    public function testBuild_success()
    {
        $result = $this->serviceBuilder->build(TestClass::class);
        self::assertInstanceOf(TestClass::class, $result);
    }

    public function testBuild_nonExistingClassThrowsException()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->serviceBuilder->build('I AM NOT A CLASS');
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
        parent::setUp();
        $this->serviceBuilder = new ServiceBuilder($this->serviceConfiguration);
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