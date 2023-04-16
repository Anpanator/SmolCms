<?php
declare(strict_types=1);

namespace SmolCms\Service\Core\Startup;

interface StartupAction
{
    public function runAction(): void;
}