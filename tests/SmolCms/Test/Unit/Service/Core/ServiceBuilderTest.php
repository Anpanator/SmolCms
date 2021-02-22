<?php

declare(strict_types=1);

namespace SmolCms\Test\Unit\Service\Core;

use InvalidArgumentException;
use PHPUnit\Framework\MockObject\MockObject;
use SmolCms\Config\ServiceConfiguration;
use SmolCms\Data\Business\Service;
use SmolCms\Data\Business\ServiceRegistry;
use SmolCms\Exception\AutowireException;
use SmolCms\Service\Core\ServiceBuilder;
use SmolCms\TestUtils\Attributes\Mock;
use SmolCms\TestUtils\SimpleTestCase;

class ServiceBuilderTest extends SimpleTestCase
{
    private ServiceBuilder $serviceBuilder;
    #[Mock(ServiceConfiguration::class)]
    private ServiceConfiguration|MockObject $serviceConfiguration;
    #[Mock(ServiceRegistry::class)]
    private ServiceRegistry|MockObject $serviceRegistry;

    public function testGetService_success()
    {
        $result = $this->serviceBuilder->getService(TestClass::class);
        self::assertInstanceOf(TestClass::class, $result);
    }

    public function testGetService_nonExistingClassThrowsException()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->serviceBuilder->getService('I AM NOT A CLASS');
    }

    public function testGetService_throwsAutowireExceptionWithScalarDependencyAndNoManualConfig()
    {
        $this->expectException(AutowireException::class);
        $this->serviceBuilder->getService(TestClassWithScalarDependencies::class);
    }

    public function testGetService_throwsAutowireExceptionWithUntypedDependencyAndNoManualConfig()
    {
        $this->expectException(AutowireException::class);
        $this->serviceBuilder->getService(TestClassWithUntypedDependencies::class);
    }


    public function testGetService_successWithConfiguration()
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

        $result = $this->serviceBuilder->getService($serviceId);
        self::assertInstanceOf(TestClassWithOnlyScalarDependencies::class, $result);
    }

    public function testGetService_successWithNestedDependenciesAndConfiguration()
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

        $result = $this->serviceBuilder->getService($serviceId);
        self::assertInstanceOf(TestClassWithScalarDependencies::class, $result);
    }

    public function testGetService_requestingSameServiceTwiceWillOnlyInstantiateOnce()
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

        $result = $this->serviceBuilder->getService($serviceId);
        self::assertInstanceOf(TestClassWithScalarDependencies::class, $result);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->serviceBuilder = new ServiceBuilder($this->serviceConfiguration, $this->serviceRegistry);
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