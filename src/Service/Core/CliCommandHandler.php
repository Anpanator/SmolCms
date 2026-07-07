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

    /**
     * Parses global CLI arguments from `$argv` into a `[flagName => [arg, arg, ...]]` map.
     *
     * Tokens prefixed with `--` are looked up in {@see CliCommandFlag}; everything else
     * is appended to the most recently seen flag's argument list. Quoted spans (single
     * or double quotes) are collapsed into a single argument, even when the closing
     * quote sits on a later argv token. A missing closing quote absorbs tokens up to
     * the next flag (or end of input).
     *
     * @return array<string, list<string>>
     */
    private function parseGlobalArgs(): array
    {
        global $argv;
        if (!isset($argv)) {
            return [];
        }

        $tokens = $this->collapseQuotedSpans(array_slice($argv, 1));

        $flags = [];
        $currentFlag = null;
        foreach ($tokens as $token) {
            $flag = $this->parseFlag($token);
            if ($flag !== null) {
                $currentFlag = $flag;
                $flags[$flag->name] ??= [];
                continue;
            }
            if ($currentFlag === null) {
                continue;
            }
            $flags[$currentFlag->name][] = $token;
        }

        return $flags;
    }

    /**
     * Returns the {@see CliCommandFlag} case matching the given `--flag` token,
     * or `null` if the token is not a flag or not a known flag.
     */
    private function parseFlag(string $token): ?CliCommandFlag
    {
        if (!str_starts_with($token, '--')) {
            return null;
        }
        return CliCommandFlag::tryFrom(substr($token, 2));
    }

    /**
     * Walks `$tokens` and merges each quoted span (e.g. `'a b'` split across
     * multiple argv tokens) into one unquoted token. A `--`-prefixed token
     * ends the current span and stays in the output so {@see parseGlobalArgs()}
     * can recognise it as a flag boundary.
     *
     * @param list<string> $tokens
     * @return list<string>
     */
    private function collapseQuotedSpans(array $tokens): array
    {
        $result = [];
        $count = count($tokens);
        $i = 0;
        while ($i < $count) {
            $token = $tokens[$i];
            if ($token !== '' && ($token[0] === "'" || $token[0] === '"')) {
                [$merged, $nextIndex] = $this->consumeQuotedSpan($tokens, $i, $count);
                $result[] = $merged;
                $i = $nextIndex;
                continue;
            }
            $result[] = $token;
            $i++;
        }
        return $result;
    }

    /**
     * Reads a quoted span beginning at `$start` and returns a `[value, nextIndex]`
     * tuple. The value has the surrounding quotes stripped. The next index points
     * to the first token after the consumed span (`$start + 1` for single-token
     * spans, the index of the matching closing quote + 1 when found, the index
     * of a `--` boundary token (which is left unconsumed), or the token count
     * if the span runs to the end of the input).
     *
     * @param list<string> $tokens
     * @return array{0: string, 1: int}
     */
    private function consumeQuotedSpan(array $tokens, int $start, int $count): array
    {
        $quote = $tokens[$start][0];
        $first = $tokens[$start];

        if (strlen($first) > 1 && str_ends_with($first, $quote)) {
            return [substr($first, 1, -1), $start + 1];
        }

        $parts = [strlen($first) > 1 ? substr($first, 1) : ''];
        $i = $start + 1;
        while ($i < $count) {
            $token = $tokens[$i];
            if (str_starts_with($token, '--')) {
                return [implode(' ', $parts), $i];
            }
            if (str_ends_with($token, $quote)) {
                $parts[] = substr($token, 0, -1);
                return [implode(' ', $parts), $i + 1];
            }
            $parts[] = $token;
            $i++;
        }
        return [implode(' ', $parts), $i];
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
}
