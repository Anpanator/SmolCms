<?php
declare(strict_types=1);

namespace SmolCms\Test\Service\Core\Action;

use PHPUnit\Framework\MockObject\MockObject;
use DateTime;
use SmolCms\Data\Constant\AccessLevel;
use SmolCms\Data\Constant\HttpStatus;
use SmolCms\Data\DTO\SessionUserData;
use SmolCms\Data\Persistence\UserEntity;
use SmolCms\Data\Request\Request;
use SmolCms\Service\Core\Action\AuthorizationPreControllerAction;
use SmolCms\Service\Core\Attribute\Authenticated;
use SmolCms\Service\Core\Session\SessionService;
use SmolCms\Service\DB\UserService;
use SmolCms\TestUtils\Attributes\Mock;
use SmolCms\TestUtils\Attributes\Stub;
use SmolCms\TestUtils\SimpleTestCase;


class AuthorizationPreControllerActionTest extends SimpleTestCase
{
    private AuthorizationPreControllerAction $action;

    #[Stub(SessionService::class)]
    private SessionService|MockObject $sessionService;

    #[Mock(UserService::class)]
    private UserService|MockObject $userService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->action = new AuthorizationPreControllerAction($this->sessionService, $this->userService);
    }

    public function testProcess_ReturnsNullWhenNoAuthenticatedAttribute(): void
    {
        $controller = new AuthorizationPreControllerActionTest_NoAuthController();
        $request = $this->createStub(Request::class);
        $handlerArguments = [];
        $this->userService
            ->expects(self::never())
            ->method('findOneById')
            ->seal();

        $result = $this->action->process($request, $controller, 'handle', $handlerArguments);

        self::assertNull($result);
    }

    public function testProcess_ReturnsUnauthorizedWhenNoUserInSession(): void
    {
        $controller = new AuthorizationPreControllerActionTest_WithAuthController();
        $request = $this->createStub(Request::class);
        $handlerArguments = [];

        $this->sessionService
            ->method('getUserData')
            ->willReturn(null)
            ->seal();
        $this->userService
            ->expects(self::never())
            ->method('findOneById')
            ->seal();

        $result = $this->action->process($request, $controller, 'restrictedFellow', $handlerArguments);

        self::assertNotNull($result);
        self::assertSame(HttpStatus::UNAUTHORIZED, $result->getStatus());
    }

    public function testProcess_ReturnsForbiddenWhenInsufficientAccessLevel(): void
    {
        $controller = new AuthorizationPreControllerActionTest_WithAuthController();
        $request = $this->createStub(Request::class);
        $handlerArguments = [];

        $userData = new SessionUserData(1, 'test', AccessLevel::NOVICE);
        $this->userService
            ->expects(self::once())
            ->method('findOneById')
            ->with(1)
            ->willReturn($this->createUser(AccessLevel::NOVICE))
            ->seal();
        $this->sessionService
            ->method('getUserData')
            ->willReturn($userData)
            ->seal();

        $result = $this->action->process($request, $controller, 'restrictedWizard', $handlerArguments);

        self::assertNotNull($result);
        self::assertSame(HttpStatus::FORBIDDEN, $result->getStatus());
    }

    public function testProcess_ReturnsNullWhenSufficientAccessLevel(): void
    {
        $controller = new AuthorizationPreControllerActionTest_WithAuthController();
        $request = $this->createStub(Request::class);
        $handlerArguments = [];

        $userData = new SessionUserData(1, 'test', AccessLevel::WIZARD);
        $this->userService
            ->expects(self::once())
            ->method('findOneById')
            ->with(1)
            ->willReturn($this->createUser(AccessLevel::WIZARD))
            ->seal();
        $this->sessionService
            ->method('getUserData')
            ->willReturn($userData)
            ->seal();

        $result = $this->action->process($request, $controller, 'restrictedWizard', $handlerArguments);

        self::assertNull($result);
    }

    public function testProcess_ReturnsNullWhenExactAccessLevelMatch(): void
    {
        $controller = new AuthorizationPreControllerActionTest_WithAuthController();
        $request = $this->createStub(Request::class);
        $handlerArguments = [];

        $userData = new SessionUserData(1, 'test', AccessLevel::FELLOW);
        $this->userService
            ->expects(self::once())
            ->method('findOneById')
            ->with(1)
            ->willReturn($this->createUser(AccessLevel::FELLOW))
            ->seal();
        $this->sessionService
            ->method('getUserData')
            ->willReturn($userData)
            ->seal();

        $result = $this->action->process($request, $controller, 'restrictedFellow', $handlerArguments);

        self::assertNull($result);
    }

    public function testProcessUsesCurrentDatabaseRoleAndRefreshesStaleSession(): void
    {
        $controller = new AuthorizationPreControllerActionTest_WithAuthController();
        $request = $this->createStub(Request::class);
        $handlerArguments = [];

        $this->sessionService
            ->method('getUserData')
            ->willReturn(new SessionUserData(1, 'Stale display name', AccessLevel::ADMIN))
            ->seal();
        $this->userService
            ->expects(self::once())
            ->method('findOneById')
            ->with(1)
            ->willReturn($this->createUser(AccessLevel::NOVICE, 'Current display name'))
            ->seal();

        $result = $this->action->process($request, $controller, 'restrictedWizard', $handlerArguments);

        self::assertNotNull($result);
        self::assertSame(HttpStatus::FORBIDDEN, $result->getStatus());
    }

    public function testProcessFailsClosedWhenSessionUserNoLongerExists(): void
    {
        $controller = new AuthorizationPreControllerActionTest_WithAuthController();
        $request = $this->createStub(Request::class);
        $handlerArguments = [];

        $this->sessionService
            ->method('getUserData')
            ->willReturn(new SessionUserData(1, 'deleted user', AccessLevel::ADMIN))
            ->seal();
        $this->userService
            ->expects(self::once())
            ->method('findOneById')
            ->with(1)
            ->willReturn(null)
            ->seal();

        $result = $this->action->process($request, $controller, 'restrictedFellow', $handlerArguments);

        self::assertNotNull($result);
        self::assertSame(HttpStatus::FORBIDDEN, $result->getStatus());
    }

    private function createUser(AccessLevel $accessLevel, string $displayName = 'test'): UserEntity
    {
        return new UserEntity(
            id: 1,
            loginName: 'test-user',
            password: 'hashed-password',
            displayName: $displayName,
            state: 'active',
            registerDate: new DateTime('2026-10-20 12:00:00'),
            lastLoginDate: null,
            accessLevel: $accessLevel,
        );
    }
}

class AuthorizationPreControllerActionTest_NoAuthController
{
    public function handle(): void
    {
    }
}

class AuthorizationPreControllerActionTest_WithAuthController
{
    #[Authenticated(AccessLevel::FELLOW)]
    public function restrictedFellow(): void
    {
    }

    #[Authenticated(AccessLevel::WIZARD)]
    public function restrictedWizard(): void
    {
    }
}
