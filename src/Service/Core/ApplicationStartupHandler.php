<?php
declare(strict_types=1);

namespace SmolCms\Service\Core;

use SmolCms\Service\Core\Startup\StartupAction;

final readonly class ApplicationStartupHandler
{
    /** @var StartupAction[] */
    private array $startupActions;

    public function __construct(
        StartupAction ...$actions
    )
    {
        $this->startupActions = $actions;
    }

    public function runActions(): void
    {
        foreach ($this->startupActions as $action) {
            $action->runAction();
        }
    }
}