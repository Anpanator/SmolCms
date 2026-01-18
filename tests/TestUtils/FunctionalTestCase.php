<?php

declare(strict_types=1);

namespace SmolCms\TestUtils;


use ReflectionObject;
use ReflectionProperty;
use SmolCms\Config\ServiceConfiguration;
use SmolCms\Data\Business\ServiceRegistry;
use SmolCms\Service\Core\ApplicationCore;
use SmolCms\Service\Core\ServiceBuilder;
use SmolCms\TestUtils\Attributes\Autowire;

class FunctionalTestCase extends SimpleTestCase
{
    protected ApplicationCore $applicationCore;
    private ServiceBuilder $serviceBuilder;

    protected function setUp(): void
    {
        parent::setUp();

        $this->serviceBuilder = new ServiceBuilder(
            new ServiceConfiguration(),
            new ServiceRegistry()
        );
        $this->applicationCore = new ApplicationCore(
            $this->serviceBuilder
        );
        $this->initAutowires();
    }

    protected function initAutowires(): void
    {
        $reflector = new ReflectionObject($this);
        /** @var ReflectionProperty $reflectionProperty */
        foreach ($reflector->getProperties() as $reflectionProperty) {
            $attributes = $reflectionProperty->getAttributes(Autowire::class);
            if (!$attributes) {
                continue;
            }
            $propertyTypeName = $reflectionProperty->getType()->getName();
            $services = $this->serviceBuilder->getService($propertyTypeName);
            $reflectionProperty->setAccessible(true);
            $reflectionProperty->setValue($this, reset($services));
        }
    }
}