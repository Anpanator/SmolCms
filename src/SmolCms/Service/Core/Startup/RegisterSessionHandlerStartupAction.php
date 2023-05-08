<?php
declare(strict_types=1);

namespace SmolCms\Service\Core\Startup;

use SmolCms\Service\Core\Session\SessionHandler;

class RegisterSessionHandlerStartupAction implements StartupAction
{

    public function __construct(
        private SessionHandler $sessionHandler
    )
    {
    }

    public function runAction(): void
    {
        session_set_save_handler($this->sessionHandler);
        // TODO: Once login is implemented, only start session when session cookie is set OR the user logs in.
        //session_start();
    }
}