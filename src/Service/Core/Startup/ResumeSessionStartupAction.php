<?php
declare(strict_types=1);

namespace SmolCms\Service\Core\Startup;

use SmolCms\Service\Core\Session\SessionHandler;
use SmolCms\Service\Core\Session\SessionService;

readonly class ResumeSessionStartupAction implements StartupAction
{

    public function __construct(
        private SessionService $sessionService,
        private SessionHandler $sessionHandler,
    )
    {
    }

    public function runAction(): void
    {
        $sessionId = $_COOKIE[SessionService::SESSION_NAME] ?? null;
        if ($sessionId !== null && (
                !is_string($sessionId)
                || $sessionId === ''
                || !$this->sessionHandler->validateId($sessionId)
            )) {
            $this->sessionService->discardStaleSessionCookie();
        }

        $this->sessionService->resumeSession();
    }
}
