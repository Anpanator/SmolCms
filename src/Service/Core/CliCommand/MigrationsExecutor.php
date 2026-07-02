<?php

declare(strict_types=1);

namespace SmolCms\Service\Core\CliCommand;

use SmolCms\Data\Constant\CliCommandFlag;

final readonly class MigrationsExecutor implements CliCommandExecutor
{
    public function handles(): CliCommandFlag
    {
        return CliCommandFlag::MIGRATIONS;
    }

    public function execute(array $arguments, bool $noconfirm = false): void
    {
        echo "Running migrations...\n";
    }
}
