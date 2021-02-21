<?php

declare(strict_types=1);

namespace SmolCms\Test\Unit\Service\Core;

use InvalidArgumentException;
use PHPUnit\Framework\MockObject\MockObject;
use SmolCms\Config\ServiceConfiguration;
use SmolCms\Data\Business\Service;
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


    public function testBuild_successWithConfiguration()
    {
        $serviceId = 'testService';
        $service = new Service(
            identifier: $serviceId,
            class: TestClassWithOnlyScalarDependencies::class,
            parameters: [1234, 'I am a String']
        );
        $this->serviceConfiguration
            ->method('getServiceByIdentifier')
            ->with($serviceId)
            ->willReturn($service);

        $result = $this->serviceBuilder->build($serviceId);
        self::assertInstanceOf(TestClassWithOnlyScalarDependencies::class, $result);
    }

    public function testBuild_successWithNestedDependenciesAndConfiguration()
    {
        $serviceId = 'TestClassWithScalarDependenciesService';
        $service = new Service(
            identifier: $serviceId,
            class: TestClassWithScalarDependencies::class,
            parameters: ['TestClassService', 1234, 'plainString']
        );
        $serviceId2 = 'TestClassService';
        $service2 = new Service(
            identifier: $serviceId2,
            class: TestClass::class,
            // Note this parameter needs to be autowired, as we didn't configure it
            parameters: [AnotherTestClass::class]
        );
        $this->serviceConfiguration
            ->method('getServiceByIdentifier')
            ->will(
                self::returnValueMap(
                    [
                        [$serviceId, $service],
                        [$serviceId2, $service2]
                    ]
                )
            );

        $result = $this->serviceBuilder->build($serviceId);
        self::assertInstanceOf(TestClassWithScalarDependencies::class, $result);
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
    public function __construct(private TestClass $testClass, private int $intVal, private string $stringVal)
    {
    }
}

final class TestClassWithUntypedDependencies
{

    public function __construct(private AnotherTestClass $anotherTestClass, private $untypedValue)
    {
    }
}

final class TestClassWithOnlyScalarDependencies
{
    public function __construct(private int $someInt, private string $someString)
    {
    }
}