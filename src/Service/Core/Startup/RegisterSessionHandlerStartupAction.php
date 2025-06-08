<?php
declare(strict_types=1);

namespace SmolCms\Service\Core\Startup;

use SmolCms\Service\Core\Session\SessionHandler;

readonly class RegisterSessionHandlerStartupAction implements StartupAction
{

    public function __construct(
        private SessionHandler $sessionHandler
    )
    {
    }

    public function runAction(): void
    {
        session_set_save_handler($this->sessionHandler);
    }
}