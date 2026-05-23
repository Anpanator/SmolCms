<?php

declare(strict_types=1);

namespace SmolCms\TestUtils;


use PDO;
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
    private bool $isFirstRun = true;

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
        // this is done here instead of setUpBeforeClass() because it's in a static context.
        // but since the DB should only be initialized once per test run, we do this workaround
        if ($this->isFirstRun) {
            $pdo = $this->serviceBuilder->getService(PDO::class);
            $pdo->exec(file_get_contents(__DIR__ . '/../../private/init/init_sqlite.sql'));
            $this->isFirstRun = false;
        }
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
            $service = $this->serviceBuilder->getService($propertyTypeName);
            $reflectionProperty->setAccessible(true);
            $reflectionProperty->setValue($this, $service);
        }
    }
}