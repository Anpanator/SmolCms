<?php
declare(strict_types=1);

namespace SmolCms\Test\Service\Core\Action;

use PHPUnit\Framework\MockObject\MockObject;
use SmolCms\Data\Constant\AccessLevel;
use SmolCms\Data\Constant\HttpStatus;
use SmolCms\Data\DTO\SessionUserData;
use SmolCms\Data\Request\Request;
use SmolCms\Service\Core\Action\AuthorizationPreControllerAction;
use SmolCms\Service\Core\Attribute\Authenticated;
use SmolCms\Service\Core\Session\SessionService;
use SmolCms\TestUtils\Attributes\Stub;
use SmolCms\TestUtils\SimpleTestCase;


class AuthorizationPreControllerActionTest extends SimpleTestCase
{
    private AuthorizationPreControllerAction $action;

    #[Stub(SessionService::class)]
    private SessionService|MockObject $sessionService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->action = new AuthorizationPreControllerAction($this->sessionService);
    }

    public function testProcess_ReturnsNullWhenNoAuthenticatedAttribute(): void
    {
        $controller = new AuthorizationPreControllerActionTest_NoAuthController();
        $request = $this->createMock(Request::class);
        $handlerArguments = [];

        $result = $this->action->process($request, $controller, 'handle', $handlerArguments);

        self::assertNull($result);
    }

    public function testProcess_ReturnsUnauthorizedWhenNoUserInSession(): void
    {
        $controller = new AuthorizationPreControllerActionTest_WithAuthController();
        $request = $this->createMock(Request::class);
        $handlerArguments = [];

        $this->sessionService
            ->method('getUserData')
            ->willReturn(null)
            ->seal();

        $result = $this->action->process($request, $controller, 'restrictedFellow', $handlerArguments);

        self::assertNotNull($result);
        self::assertSame(HttpStatus::UNAUTHORIZED, $result->getStatus());
    }

    public function testProcess_ReturnsForbiddenWhenInsufficientAccessLevel(): void
    {
        $controller = new AuthorizationPreControllerActionTest_WithAuthController();
        $request = $this->createMock(Request::class);
        $handlerArguments = [];

        $userData = new SessionUserData(1, 'test', AccessLevel::NOVICE);
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
        $request = $this->createMock(Request::class);
        $handlerArguments = [];

        $userData = new SessionUserData(1, 'test', AccessLevel::WIZARD);
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
        $request = $this->createMock(Request::class);
        $handlerArguments = [];

        $userData = new SessionUserData(1, 'test', AccessLevel::FELLOW);
        $this->sessionService
            ->method('getUserData')
            ->willReturn($userData)
            ->seal();

        $result = $this->action->process($request, $controller, 'restrictedFellow', $handlerArguments);

        self::assertNull($result);
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