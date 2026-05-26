<?php

declare(strict_types=1);

namespace SmolCms\TestUtils;


use PDO;
use ReflectionObject;
use SmolCms\Config\CoreServiceConfiguration;
use SmolCms\Data\Business\Service;
use SmolCms\Data\Business\ServiceRegistry;
use SmolCms\Data\Request\Request;
use SmolCms\Data\Response\Response;
use SmolCms\Service\Core\ApplicationCore;
use SmolCms\Service\Core\ServiceBuilder;
use SmolCms\TestUtils\Attributes\Autowire;

class FunctionalTestCase extends SimpleTestCase
{
    private static ApplicationCore $applicationCore;
    private static ServiceBuilder $serviceBuilder;
    private static CoreServiceConfiguration $serviceConfiguration;
    private bool $isFirstRun = true;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::$serviceConfiguration = new CoreServiceConfiguration();
        self::$serviceBuilder = new ServiceBuilder(
            self::$serviceConfiguration,
            new ServiceRegistry()
        );
        self::$applicationCore = new ApplicationCore(
            self::$serviceBuilder
        );

        self::registerTestServices(
            new Service(
                identifier: PDO::class,
                class: null,
                parameters: [
                    'sqlite::memory:',
                ]
            ),
        );

        $pdo = self::$serviceBuilder->getService(PDO::class);
        $pdo->exec(file_get_contents(__DIR__ . '/../../private/init/init_sqlite.sql'));
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->initAutowires();
    }

    protected function initAutowires(): void
    {
        $reflector = new ReflectionObject($this);
        foreach ($reflector->getProperties() as $reflectionProperty) {
            $attributes = $reflectionProperty->getAttributes(Autowire::class);
            if (!$attributes) {
                continue;
            }
            $propertyTypeName = $reflectionProperty->getType()->getName();
            $service = self::$serviceBuilder->getService($propertyTypeName);
            $reflectionProperty->setValue($this, $service);
        }
    }

    protected static function registerTestServices(Service ...$services): void
    {
        foreach ($services as $service) {
            self::$serviceConfiguration->addService($service, true);
        }
    }

    protected function simulateRequest(Request $request): Response
    {
        return self::$applicationCore->simulateRequest($request);
    }
}