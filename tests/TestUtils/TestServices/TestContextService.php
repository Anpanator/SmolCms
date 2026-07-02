<?php
declare(strict_types=1);

namespace SmolCms\TestUtils\TestServices;

use SmolCms\Service\Core\ContextService;

class TestContextService extends ContextService
{
    private bool $cliMode = true;

    public function setIsCliMode(bool $cliMode): void
    {
        $this->cliMode = $cliMode;
    }

    public function isCliMode(): bool
    {
        return $this->cliMode;
    }
}
