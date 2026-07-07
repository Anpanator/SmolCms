<?php

declare(strict_types=1);

namespace SmolCms\Service\Core;

use SmolCms\Data\Constant\CliCommandFlag;
use SmolCms\Service\Core\CliCommand\CliCommandExecutor;

final class CliCommandHandler
{
    /** @var array<string, list<string>> */
    private array $flags = [];

    /** @var array<string, CliCommandExecutor> */
    private array $executors = [];

    private ContextService $contextService;

    public function __construct(ContextService $contextService, CliCommandExecutor ...$executors)
    {
        $this->contextService = $contextService;
        foreach ($executors as $executor) {
            $this->executors[$executor->handles()->name] = $executor;
        }
    }

    public function runCommand(): bool
    {
        if (!$this->contextService->isCliMode()) {
            return false;
        }

        $this->flags = $this->parseGlobalArgs();

        $noconfirm = isset($this->flags[CliCommandFlag::NOCONFIRM->name]);

        $executed = false;
        foreach ($this->flags as $flag => $arguments) {
            if (isset($this->executors[$flag])) {
                $this->executors[$flag]->execute($arguments, $noconfirm);
                $executed = true;
            }
        }

        if (!$executed) {
            echo "Available commands:\n";
            foreach ($this->executors as $executor) {
                $flag = $executor->handles()->value;
                echo "  --$flag  {$executor->helptext()}\n";
            }
            echo "  --noconfirm  Skip confirmation prompts.\n";
        }
        return true;
    }

    private function parseGlobalArgs(): array
    {
        global $argv;
        $flags = [];
        if (!isset($argv)) {
            return $flags;
        }
        $result = [];

        $args = array_slice($argv, 1);
        $currentFlag = null;
        foreach ($args as $arg) {
            $isNewFlag = str_starts_with($arg, '--');
            if (isset($currentFlag) && !$isNewFlag) {
                $result[$currentFlag->name][] = $arg;
                continue;
            }

            if ($isNewFlag && $currentFlag = CliCommandFlag::tryFrom(substr($arg, 2))) {
                $result[$currentFlag->name] = [];
                continue;
            }
        }
        return $result;
    }
}
