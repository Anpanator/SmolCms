<?php

declare(strict_types=1);

namespace SmolCms\Test\Service\Core\Session;

use SmolCms\Data\DTO\SessionUserData;
use SmolCms\Exception\InvalidStateException;
use SmolCms\Service\Core\Session\SessionService;
use SmolCms\TestUtils\SimpleTestCase;

class SessionServiceTest extends SimpleTestCase
{
    private SessionService $sessionService;

    /** @var array<string, mixed> */
    private array $originalCookie;

    /** @var array<string, mixed> */
    private array $originalSession;

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalCookie = $_COOKIE;
        $this->originalSession = $_SESSION ?? [];
        $this->closeActiveSession();
        $_COOKIE = [];
        $_SESSION = [];

        $this->sessionService = new SessionService();
    }

    protected function tearDown(): void
    {
        $this->closeActiveSession();
        $_COOKIE = $this->originalCookie;
        $_SESSION = $this->originalSession;

        parent::tearDown();
    }

    public function testResumeSessionWithoutCookie_DoesNotStartSession(): void
    {
        $this->sessionService->resumeSession();

        self::assertSame(PHP_SESSION_NONE, session_status());
        self::assertSame([], $_SESSION);
    }

    public function testResumeSessionWithCookie_RestoresSession(): void
    {
        $this->sessionService->startSession();
        $sessionId = session_id();
        $_SESSION['marker'] = 'value';
        self::assertTrue(session_write_close());

        $_SESSION = [];
        $_COOKIE['session'] = $sessionId;

        $this->sessionService->resumeSession();

        self::assertSame(PHP_SESSION_ACTIVE, session_status());
        self::assertSame($sessionId, session_id());
        self::assertSame('value', $_SESSION['marker']);
    }

    public function testStartSession_ThrowsWhenAlreadyActive(): void
    {
        $this->sessionService->startSession();

        $this->expectException(InvalidStateException::class);
        $this->expectExceptionMessageIsOrContains('Session already started.');
        $this->sessionService->startSession();
    }

    public function testDestroySession_ClearsDataAndClosesSession(): void
    {
        $this->sessionService->startSession();
        $_SESSION['temporary'] = 'value';
        $this->sessionService->setUserData(new SessionUserData(1, 'Test User'));

        $this->sessionService->destroySession();

        self::assertSame(PHP_SESSION_NONE, session_status());
        self::assertSame([], $_SESSION);
        self::assertNull($this->sessionService->getUserData());
    }

    private function closeActiveSession(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return;
        }

        $_SESSION = [];
        session_destroy();
    }
}
