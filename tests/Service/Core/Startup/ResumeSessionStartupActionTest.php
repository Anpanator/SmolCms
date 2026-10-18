<?php
declare(strict_types=1);

namespace SmolCms\Test\Service\Core\Startup;

use SmolCms\Service\Core\Session\SessionHandler;
use SmolCms\Service\Core\Session\SessionService;
use SmolCms\Service\Core\Startup\ResumeSessionStartupAction;
use SmolCms\TestUtils\Attributes\Autowire;
use SmolCms\TestUtils\FunctionalTestCase;

class ResumeSessionStartupActionTest extends FunctionalTestCase
{
    #[Autowire]
    private SessionHandler $sessionHandler;

    #[Autowire]
    private SessionService $sessionService;

    /** @var array<string, mixed> */
    private array $originalCookie;

    protected function setUp(): void
    {
        $this->originalCookie = $_COOKIE;
        $_COOKIE = [];
        parent::setUp();
    }

    protected function tearDown(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION = [];
            session_destroy();
        }
        $_COOKIE = $this->originalCookie;
        parent::tearDown();
    }

    public function testRunAction_DiscardsStaleCookieWithoutStartingSession(): void
    {
        $_COOKIE[SessionService::SESSION_NAME] = 'missing-session-id';
        $action = new ResumeSessionStartupAction($this->sessionService, $this->sessionHandler);

        $action->runAction();

        self::assertArrayNotHasKey(SessionService::SESSION_NAME, $_COOKIE);
        self::assertSame(PHP_SESSION_NONE, session_status());
    }

    public function testRunAction_ResumesAnExistingSession(): void
    {
        $this->sessionService->startSession();
        $sessionId = session_id();
        $_SESSION['marker'] = 'value';
        self::assertTrue(session_write_close());

        $_SESSION = [];
        $_COOKIE[SessionService::SESSION_NAME] = $sessionId;
        $action = new ResumeSessionStartupAction($this->sessionService, $this->sessionHandler);

        $action->runAction();

        self::assertSame(PHP_SESSION_ACTIVE, session_status());
        self::assertSame('value', $_SESSION['marker']);
    }
}
