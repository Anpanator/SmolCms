<?php

declare(strict_types=1);

namespace SmolCms\Test\Integration\Service\Core;

use SmolCms\Config\ServiceConfiguration;
use SmolCms\Data\Business\ServiceRegistry;
use SmolCms\Service\Core\ServiceBuilder;
use SmolCms\TestUtils\SimpleTestCase;

class ServiceBuilderTest extends SimpleTestCase
{

    public function test__construct_willRegisterOwnServiceRegistryAndConfig()
    {
        $serviceConfiguration = new ServiceConfiguration();
        $serviceRegistry = new ServiceRegistry();
        $serviceBuilder = new ServiceBuilder($serviceConfiguration, $serviceRegistry);

        self::assertSame($serviceRegistry, $serviceBuilder->getService(ServiceRegistry::class));
        self::assertSame($serviceConfiguration, $serviceBuilder->getService(ServiceConfiguration::class));
    }
}
