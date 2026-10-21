<?php

declare(strict_types=1);

namespace SmolCms\Test\Service\Core;

use SmolCms\Config\CoreServiceConfiguration;
use SmolCms\Data\Business\ServiceRegistry;
use SmolCms\Service\Core\ServiceBuilder;
use SmolCms\Service\Core\Startup\SetCrossOriginResourcePolicyStartupAction;
use SmolCms\TestUtils\SimpleTestCase;

class ServiceBuilderIntegrationTest extends SimpleTestCase
{

    public function test__construct_willRegisterOwnServiceRegistryAndConfig()
    {
        $serviceConfiguration = new CoreServiceConfiguration();
        $serviceRegistry = new ServiceRegistry();
        $serviceBuilder = new ServiceBuilder($serviceConfiguration, $serviceRegistry);

        self::assertSame($serviceRegistry, $serviceBuilder->getService(ServiceRegistry::class));
        self::assertSame($serviceConfiguration, $serviceBuilder->getService(CoreServiceConfiguration::class));
    }

    public function testCoreConfigurationRegistersCrossOriginResourcePolicyActionWithoutArguments(): void
    {
        $serviceConfiguration = new CoreServiceConfiguration();
        $service = $serviceConfiguration->getServiceByIdentifier(SetCrossOriginResourcePolicyStartupAction::class);
        self::assertNotNull($service);
        self::assertSame([], $service->getParameters());

        $serviceBuilder = new ServiceBuilder($serviceConfiguration, new ServiceRegistry());

        self::assertInstanceOf(
            SetCrossOriginResourcePolicyStartupAction::class,
            $serviceBuilder->getService(SetCrossOriginResourcePolicyStartupAction::class),
        );
    }
}
