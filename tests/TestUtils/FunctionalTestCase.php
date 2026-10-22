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
use SmolCms\Data\Constant\HttpStatus;
use SmolCms\Data\Persistence\UserEntity;
use SmolCms\Data\Request\Request;
use SmolCms\Data\Response\Response;
use SmolCms\Service\Core\Authentication\PasswordService;
use SmolCms\Service\Core\ApplicationCore;
use SmolCms\Service\Core\ContextService;
use SmolCms\Service\Core\ServiceBuilder;
use SmolCms\Service\Core\Startup\RestrictDirectoryAccessStartupAction;
use SmolCms\Service\DB\UserService;
use SmolCms\TestUtils\Attributes\Autowire;
use SmolCms\TestUtils\TestServices\TestContextService;
use SmolCms\TestUtils\TestServices\TestPasswordService;

class FunctionalTestCase extends SimpleTestCase
{
    private static ApplicationCore $applicationCore;
    private static ServiceBuilder $serviceBuilder;
    private static CoreServiceConfiguration $serviceConfiguration;
    private static TestContextService $contextService;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        // Whitelisted directories are runtime directories (untracked); in a fresh checkout
        // they may be absent, and FileSystemAccessService refuses to be constructed when
        // an allowed directory does not exist.
        foreach (FS_APP_LEVEL_WHITELIST as $allowedDir) {
            if (!is_dir($allowedDir)) {
                mkdir($allowedDir, 0777, true);
            }
        }

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
                identifier: PasswordService::class,
                class: TestPasswordService::class,
                parameters: [UserService::class]
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

    /**
     * Dispatches an already-parsed request through ApplicationCore.
     *
     * This does not invoke RequestFactory::buildRequestFromGlobals() or read
     * php://input. Tests must provide postParams as parsed values; Content-Type
     * headers may exercise controller/pre-controller behavior but do not select
     * request-body parsing here. Raw JSON/form decoding belongs in RequestFactory
     * seam tests, since php://input cannot be supplied by this in-process helper.
     */
    protected function simulateRequest(Request $request): Response
    {
        self::setCliModeTest(false);
        $response = self::$applicationCore->simulateRequest($request);
        if (in_array($response->getStatus(), [
            HttpStatus::BAD_REQUEST,
            HttpStatus::UNAUTHORIZED,
            HttpStatus::FORBIDDEN,
            HttpStatus::NOT_FOUND,
            HttpStatus::CONFLICT,
            HttpStatus::INTERNAL_SERVER_ERROR,
        ], true)) {
            self::assertNull(
                $response->getContent(),
                sprintf('HTTP %d framework error responses must have an empty body.', $response->getStatus()->value),
            );
        }
        return $response;
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
                password: $this->hashTestPassword($password),
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

    protected function hashTestPassword(string $password): string
    {
        /** @var PasswordService $passwordService */
        $passwordService = self::$serviceBuilder->getService(PasswordService::class);
        return $passwordService->hash($password);
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
