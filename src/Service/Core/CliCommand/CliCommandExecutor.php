<?php

declare(strict_types=1);

namespace SmolCms\Service\Core\CliCommand;

use SmolCms\Data\Constant\CliCommandFlag;

interface CliCommandExecutor
{
    public function handles(): CliCommandFlag;

    public function execute(array $arguments, bool $noconfirm = false): void;
}
