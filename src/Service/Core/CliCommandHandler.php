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

    private function parseGlobalArgs(): array
    {
        global $argv;
        $flags = [];
        if (!isset($argv)) {
            return $flags;
        }

        $args = array_slice($argv, 1);
        $count = count($args);
        $currentFlag = null;

        for ($i = 0; $i < $count; $i++) {
            $arg = $args[$i];

            if (str_starts_with($arg, '--')) {
                $flag = CliCommandFlag::tryFrom(substr($arg, 2));
                $currentFlag = $flag;
                if ($flag !== null) {
                    $flags[$flag->name] ??= [];
                }
                continue;
            }

            if ($currentFlag === null) {
                continue;
            }

            $flags[$currentFlag->name][] = $this->resolveQuotedArgument($args, $i, $count);
        }

        return $flags;
    }

    private function resolveQuotedArgument(array $args, int &$i, int $count): string
    {
        $arg = $args[$i];
        $quote = $arg[0];

        if ($quote !== "'" && $quote !== '"') {
            return $arg;
        }

        if (str_ends_with($arg, $quote) && strlen($arg) > 1) {
            return substr($arg, 1, -1);
        }

        $parts = [strlen($arg) > 1 ? substr($arg, 1) : ''];
        $i++;
        while ($i < $count) {
            $next = $args[$i];
            if (str_starts_with($next, '--')) {
                $i--;
                break;
            }
            if (str_ends_with($next, $quote)) {
                $parts[] = substr($next, 0, -1);
                break;
            }
            $parts[] = $next;
            $i++;
        }

        return implode(' ', $parts);
    }

    public function runCommand(): void
    {
        if (!$this->contextService->isCliMode()) {
            return;
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
            $available = implode(', ', array_map(
                fn(CliCommandFlag $f) => '--' . $f->value,
                CliCommandFlag::cases()
            ));
            echo "No command specified. Available flags: $available\n";
        }
    }
}
