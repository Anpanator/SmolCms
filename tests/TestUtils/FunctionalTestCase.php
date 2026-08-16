<?php

declare(strict_types=1);

namespace SmolCms\TestUtils;


use DateTime;
use PDO;
use ReflectionObject;
use SmolCms\Config\CoreServiceConfiguration;
use SmolCms\Data\Business\Service;
use SmolCms\Data\Business\ServiceRegistry;
use SmolCms\Data\Business\Url;
use SmolCms\Data\Constant\AccessLevel;
use SmolCms\Data\Constant\HttpMethod;
use SmolCms\Data\Persistence\UserEntity;
use SmolCms\Data\Request\Request;
use SmolCms\Data\Response\Response;
use SmolCms\Service\Core\ApplicationCore;
use SmolCms\Service\Core\ContextService;
use SmolCms\Service\Core\ServiceBuilder;
use SmolCms\Service\Core\Startup\RestrictDirectoryAccessStartupAction;
use SmolCms\Service\DB\UserService;
use SmolCms\TestUtils\Attributes\Autowire;
use SmolCms\TestUtils\TestServices\TestContextService;

class FunctionalTestCase extends SimpleTestCase
{
    private static ApplicationCore $applicationCore;
    private static ServiceBuilder $serviceBuilder;
    private static CoreServiceConfiguration $serviceConfiguration;
    private static TestContextService $contextService;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::$serviceConfiguration = new CoreServiceConfiguration();

        self::registerTestServices(
            new Service(
                identifier: PDO::class,
                class: null,
                parameters: [
                    'sqlite::memory:',
                ]
            ),
            new Service(
                identifier: ContextService::class,
                class: TestContextService::class,
                parameters: []
            ),
            new Service(
                identifier: RestrictDirectoryAccessStartupAction::class,
                class: null,
                parameters: [
                    '/'
                ]
            )
        );
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->destroySessions();
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

    private static function destroySessions(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION = [];
            session_destroy();
        }
    }

    protected static function registerTestServices(Service ...$services): void
    {
        foreach ($services as $service) {
            self::$serviceConfiguration->addService($service, true);
        }

        // Application core needs to be reset after services are updated
        self::initCore();
    }

    protected static function setCliModeTest(bool $isCliMode): void
    {
        self::$contextService->setIsCliMode($isCliMode);
    }

    protected function simulateRequest(Request $request): Response
    {
        self::setCliModeTest(false);
        return self::$applicationCore->simulateRequest($request);
    }

    protected function loginUser(string $loginName, string $displayName): void
    {
        $password = 'secure-password-123';
        $userService = self::$serviceBuilder->getService(UserService::class);

        $existingUser = $userService->findOneByLoginName($loginName);
        if ($existingUser !== null) {
            $user = $existingUser;
        } else {
            $user = new UserEntity(
                id: null,
                loginName: $loginName,
                password: password_hash($password, PASSWORD_DEFAULT),
                displayName: $displayName,
                state: 'active',
                registerDate: new DateTime(),
                lastLoginDate: null,
                accessLevel: AccessLevel::NOVICE,
            );
            $userService->saveAsNew($user);
        }

        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION = [];
            session_destroy();
        }

        $loginUrl = new Url(protocol: 'https', host: 'localhost', path: '/login');
        $loginRequest = new Request(
            url: $loginUrl,
            method: HttpMethod::POST,
            postParams: [
                'loginName' => $loginName,
                'password' => $password,
            ]
        );
        $this->simulateRequest($loginRequest);
    }

    private static function initCore(): void
    {
        self::destroySessions();
        self::$serviceBuilder = new ServiceBuilder(
            self::$serviceConfiguration,
            new ServiceRegistry()
        );
        self::$contextService = self::$serviceBuilder->getService(ContextService::class);
        self::$applicationCore = self::$serviceBuilder->getService(ApplicationCore::class);
        self::$applicationCore->init();
        $pdo = self::$serviceBuilder->getService(PDO::class);
        $pdo->exec(file_get_contents(ROOT_DIR . '/private/init/init_sqlite.sql'));
    }
}