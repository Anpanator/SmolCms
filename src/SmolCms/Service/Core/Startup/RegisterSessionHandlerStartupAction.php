<?php
declare(strict_types=1);

namespace SmolCms\Service\Core\Startup;

use Exception;

class RegisterSessionHandlerStartupAction implements StartupAction
{

    public function runAction(): void
    {
        throw new Exception("Implement SessionHandler");
        // TODO: Implement runAction() method.
    }
}