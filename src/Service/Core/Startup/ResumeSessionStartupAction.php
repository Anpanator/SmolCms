<?php
declare(strict_types=1);

namespace SmolCms\Service\Core\Startup;

use SmolCms\Service\Core\Session\SessionService;

readonly class ResumeSessionStartupAction implements StartupAction
{

    public function __construct(
        private SessionService $sessionService
    )
    {
    }

    public function runAction(): void
    {
        $this->sessionService->resumeSession();
    }
}