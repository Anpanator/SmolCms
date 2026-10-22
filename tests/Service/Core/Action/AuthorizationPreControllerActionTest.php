<?php

declare(strict_types=1);

namespace SmolCms\Test\Service\Core\Action;

use DateTime;
use PHPUnit\Framework\MockObject\MockObject;
use SmolCms\Data\Constant\AccessLevel;
use SmolCms\Data\Constant\HttpStatus;
use SmolCms\Data\DTO\SessionUserData;
use SmolCms\Data\Persistence\UserEntity;
use SmolCms\Data\Request\Request;
use SmolCms\Service\Core\Action\AuthorizationPreControllerAction;
use SmolCms\Service\Core\Authentication\ApiKeyAuthenticationService;
use SmolCms\Service\Core\Authentication\SessionAuthenticationService;
use SmolCms\Service\Core\Attribute\Authenticated;
use SmolCms\Service\Core\ContextService;
use SmolCms\Service\Core\Session\SessionService;
use SmolCms\Service\DB\UserService;
use SmolCms\TestUtils\Attributes\Mock;
use SmolCms\TestUtils\Attributes\Stub;
use SmolCms\TestUtils\SimpleTestCase;

class AuthorizationPreControllerActionTest extends SimpleTestCase
{
    private AuthorizationPreControllerAction $action;

    #[Mock(SessionAuthenticationService::class)]
    private SessionAuthenticationService|MockObject $sessionAuthenticationService;

    #[Mock(ApiKeyAuthenticationService::class)]
    private ApiKeyAuthenticationService|MockObject $apiKeyAuthenticationService;

    #[Mock(SessionService::class)]
    private SessionService|MockObject $sessionService;

    #[Mock(UserService::class)]
    private UserService|MockObject $userService;

    #[Stub(ContextService::class)]
    private ContextService|MockObject $contextService;

    #[Stub(Request::class)]
    private Request|MockObject $request;

    protected function setUp(): void
    {
        parent::setUp();
        $this->action = new AuthorizationPreControllerAction(
            $this->sessionAuthenticationService,
            $this->apiKeyAuthenticationService,
            $this->sessionService,
            $this->userService,
            $this->contextService,
        );
    }

    public function testProcessReturnsNullWhenNoAuthenticatedAttribute(): void
    {
        $controller = new AuthorizationPreControllerActionTest_NoAuthController();
        $request = $this->request;
        $handlerArguments = [];
        $this->sessionAuthenticationService
            ->expects(self::never())
            ->method('resolve')
            ->seal();
        $this->apiKeyAuthenticationService
            ->expects(self::never())
            ->method('resolve')
            ->seal();
        $this->userService
            ->expects(self::never())
            ->method('findOneById')
            ->seal();
        $this->sessionService->expects(self::never())->method('destroySession');

        $result = $this->action->process($request, $controller, 'handle', $handlerArguments);

        self::assertNull($result);
    }

    public function testProcessReturnsUnauthorizedWhenNoSessionOrApiKeyIsPresent(): void
    {
        $controller = new AuthorizationPreControllerActionTest_WithAuthController();
        $request = $this->request;
        $handlerArguments = [];
        $this->sessionAuthenticationService
            ->expects(self::once())
            ->method('resolve')
            ->willReturn(null)
            ->seal();
        $this->apiKeyAuthenticationService
            ->expects(self::once())
            ->method('resolve')
            ->with(self::identicalTo($request))
            ->willReturn(null)
            ->seal();
        $this->userService
            ->expects(self::never())
            ->method('findOneById')
            ->seal();
        $this->sessionService->expects(self::never())->method('setUserData');

        $result = $this->action->process($request, $controller, 'apiRestrictedFellow', $handlerArguments);

        self::assertNotNull($result);
        self::assertSame(HttpStatus::UNAUTHORIZED, $result->getStatus());
    }

    public function testProcessDoesNotConsultApiKeyDelegateWhenEndpointIsNotApiEnabled(): void
    {
        $controller = new AuthorizationPreControllerActionTest_WithAuthController();
        $request = $this->request;
        $handlerArguments = [];
        $this->sessionAuthenticationService
            ->expects(self::once())
            ->method('resolve')
            ->willReturn(null)
            ->seal();
        $this->apiKeyAuthenticationService
            ->expects(self::never())
            ->method('resolve')
            ->seal();
        $this->userService
            ->expects(self::never())
            ->method('findOneById')
            ->seal();
        $this->sessionService->expects(self::never())->method('setUserData');

        $result = $this->action->process($request, $controller, 'restrictedFellow', $handlerArguments);

        self::assertNotNull($result);
        self::assertSame(HttpStatus::UNAUTHORIZED, $result->getStatus());
    }

    public function testProcessReturnsNullWhenSessionUserHasSufficientAccessLevel(): void
    {
        $controller = new AuthorizationPreControllerActionTest_WithAuthController();
        $request = $this->request;
        $handlerArguments = [];
        $this->sessionAuthenticationService
            ->expects(self::once())
            ->method('resolve')
            ->willReturn(new SessionUserData(1, 'Stale name', AccessLevel::NOVICE))
            ->seal();
        $this->apiKeyAuthenticationService->expects(self::never())->method('resolve');
        $this->userService
            ->expects(self::once())
            ->method('findOneById')
            ->with(1)
            ->willReturn($this->createUser(AccessLevel::WIZARD, 'Fresh name'))
            ->seal();
        $this->sessionService->expects(self::once())->method('setUserData');
        $this->sessionService->expects(self::never())->method('destroySession');

        $result = $this->action->process($request, $controller, 'restrictedFellow', $handlerArguments);

        self::assertNull($result);
    }

    public function testProcessReturnsNullWhenSessionUserMatchesRequiredAccessLevel(): void
    {
        $controller = new AuthorizationPreControllerActionTest_WithAuthController();
        $request = $this->request;
        $handlerArguments = [];
        $this->sessionAuthenticationService
            ->expects(self::once())
            ->method('resolve')
            ->willReturn(new SessionUserData(1, 'Session name', AccessLevel::FELLOW))
            ->seal();
        $this->apiKeyAuthenticationService->expects(self::never())->method('resolve');
        $this->userService
            ->expects(self::once())
            ->method('findOneById')
            ->with(1)
            ->willReturn($this->createUser(AccessLevel::FELLOW, 'Session name'))
            ->seal();
        $this->sessionService->expects(self::once())->method('setUserData');
        $this->sessionService->expects(self::never())->method('destroySession');

        $result = $this->action->process($request, $controller, 'restrictedFellow', $handlerArguments);

        self::assertNull($result);
    }

    public function testProcessAcceptsApiKeyAndRefreshesSessionData(): void
    {
        $controller = new AuthorizationPreControllerActionTest_WithAuthController();
        $request = $this->request;
        $handlerArguments = [];
        $this->sessionAuthenticationService
            ->expects(self::once())
            ->method('resolve')
            ->willReturn(null)
            ->seal();
        $this->apiKeyAuthenticationService
            ->expects(self::once())
            ->method('resolve')
            ->with(self::identicalTo($request))
            ->willReturn(new SessionUserData(1, 'Stale name', AccessLevel::NOVICE))
            ->seal();
        $this->userService
            ->expects(self::once())
            ->method('findOneById')
            ->with(1)
            ->willReturn($this->createUser(AccessLevel::FELLOW, 'Fresh name'))
            ->seal();
        $this->sessionService
            ->expects(self::once())
            ->method('setUserData')
            ->with(self::callback(static fn(SessionUserData $data): bool => (
                $data->id === 1
                && $data->displayName === 'Fresh name'
                && $data->accessLevel === AccessLevel::FELLOW
            )));
        $this->sessionService
            ->expects(self::never())
            ->method('destroySession');
        $this->sessionService
            ->expects(self::never())
            ->method('startSession');
        $this->sessionService
            ->expects(self::never())
            ->method('resumeSession');

        $result = $this->action->process($request, $controller, 'apiRestrictedFellow', $handlerArguments);

        self::assertNull($result);
    }

    public function testProcessForbidsApiKeyUserBelowRequiredAccessLevel(): void
    {
        $controller = new AuthorizationPreControllerActionTest_WithAuthController();
        $request = $this->request;
        $handlerArguments = [];
        $this->sessionAuthenticationService
            ->expects(self::once())
            ->method('resolve')
            ->willReturn(null);
        $this->apiKeyAuthenticationService
            ->expects(self::once())
            ->method('resolve')
            ->willReturn(new SessionUserData(1, 'Test', AccessLevel::WIZARD))
            ->seal();
        $this->userService
            ->expects(self::once())
            ->method('findOneById')
            ->with(1)
            ->willReturn($this->createUser(AccessLevel::FELLOW))
            ->seal();
        $this->sessionService
            ->expects(self::once())
            ->method('setUserData');
        $this->sessionService
            ->expects(self::never())
            ->method('destroySession');

        $result = $this->action->process($request, $controller, 'apiRestrictedWizard', $handlerArguments);

        self::assertNotNull($result);
        self::assertSame(HttpStatus::FORBIDDEN, $result->getStatus());
    }

    public function testProcessUsesSessionUserAndCurrentDatabaseAccessLevel(): void
    {
        $controller = new AuthorizationPreControllerActionTest_WithAuthController();
        $request = $this->request;
        $handlerArguments = [];
        $this->sessionAuthenticationService
            ->expects(self::once())
            ->method('resolve')
            ->willReturn(new SessionUserData(1, 'Stale display name', AccessLevel::ADMIN))
            ->seal();
        $this->apiKeyAuthenticationService
            ->expects(self::never())
            ->method('resolve')
            ->seal();
        $this->userService
            ->expects(self::once())
            ->method('findOneById')
            ->with(1)
            ->willReturn($this->createUser(AccessLevel::NOVICE, 'Current display name'))
            ->seal();
        $this->sessionService
            ->expects(self::once())
            ->method('setUserData')
            ->with(self::callback(static fn(SessionUserData $data): bool => (
                $data->displayName === 'Current display name'
                && $data->accessLevel === AccessLevel::NOVICE
            )));

        $result = $this->action->process($request, $controller, 'restrictedWizard', $handlerArguments);

        self::assertNotNull($result);
        self::assertSame(HttpStatus::FORBIDDEN, $result->getStatus());
    }

    public function testProcessFailsClosedAndDestroysSessionWhenSessionUserNoLongerExists(): void
    {
        $controller = new AuthorizationPreControllerActionTest_WithAuthController();
        $request = $this->request;
        $handlerArguments = [];
        $this->sessionAuthenticationService
            ->expects(self::once())
            ->method('resolve')
            ->willReturn(new SessionUserData(1, 'deleted user', AccessLevel::ADMIN));
        $this->apiKeyAuthenticationService
            ->expects(self::never())
            ->method('resolve')
            ->seal();
        $this->userService
            ->expects(self::once())
            ->method('findOneById')
            ->with(1)
            ->willReturn(null)
            ->seal();
        $this->sessionService
            ->expects(self::once())
            ->method('destroySession')
            ->seal();

        $result = $this->action->process($request, $controller, 'restrictedFellow', $handlerArguments);

        self::assertNotNull($result);
        self::assertSame(HttpStatus::FORBIDDEN, $result->getStatus());
    }

    public function testProcessFailsClosedWithoutDestroyingSessionWhenApiKeyOwnerNoLongerExists(): void
    {
        $controller = new AuthorizationPreControllerActionTest_WithAuthController();
        $request = $this->request;
        $handlerArguments = [];
        $this->sessionAuthenticationService
            ->expects(self::once())
            ->method('resolve')
            ->willReturn(null);
        $this->apiKeyAuthenticationService
            ->expects(self::once())
            ->method('resolve')
            ->willReturn(new SessionUserData(1, 'deleted user', AccessLevel::WIZARD))
            ->seal();
        $this->userService
            ->expects(self::once())
            ->method('findOneById')
            ->with(1)
            ->willReturn(null)
            ->seal();
        $this->sessionService
            ->expects(self::never())
            ->method('destroySession');

        $result = $this->action->process($request, $controller, 'apiRestrictedFellow', $handlerArguments);

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

    #[Authenticated(AccessLevel::FELLOW, apiEnabled: true)]
    public function apiRestrictedFellow(): void
    {
    }

    #[Authenticated(AccessLevel::WIZARD, apiEnabled: true)]
    public function apiRestrictedWizard(): void
    {
    }
}
