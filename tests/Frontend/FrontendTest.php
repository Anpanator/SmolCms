<?php

declare(strict_types=1);

namespace SmolCms\Test\Frontend;

use SmolCms\TestUtils\SimpleTestCase;

use function escapeshellarg;
use function getenv;
use function is_executable;
use function preg_match;
use function shell_exec;
use function sprintf;
use function str_contains;
use function trim;

final class FrontendTest extends SimpleTestCase
{
    private const CHROMIUM_FLAGS = '--headless=new --no-sandbox --disable-gpu --allow-file-access-from-files --virtual-time-budget=20000 --dump-dom';

    public function testFrontendSuiteIsGreenInHeadlessChromium(): void
    {
        $binary = $this->findChromiumBinary();
        if ($binary === null) {
            self::fail(
                'No Chromium binary found. Set the CHROMIUM_BINARY environment variable '
                . 'or install chromium so it can be located via "command -v chromium". '
                . 'The frontend test suite requires headless Chromium.'
            );
        }

        $runnerPath = ROOT_DIR . '/tests/frontend/runner.html';
        self::assertFileExists($runnerPath);

        $command = sprintf(
            '%s %s file://%s 2>/dev/null',
            escapeshellarg($binary),
            self::CHROMIUM_FLAGS,
            $runnerPath,
        );
        $output = shell_exec($command);
        self::assertIsString($output, 'Chromium did not produce any output.');
        self::assertNotSame('', trim($output), 'Chromium output was empty.');

        self::assertTrue(
            str_contains($output, 'data-complete="true"'),
            'The frontend test runner did not finish (missing data-complete="true"). '
            . 'A syntax error or import failure in a test module can cause this.'
        );

        self::assertSame(1, preg_match('/<script id="test-results"[^>]*>(.*?)<\/script>/s', $output, $matches));
        $results = json_decode($matches[1], true);
        self::assertIsArray($results, 'test-results script does not contain valid JSON: ' . $matches[1]);
        self::assertArrayHasKey('total', $results);
        self::assertArrayHasKey('passed', $results);
        self::assertArrayHasKey('failed', $results);
        self::assertArrayHasKey('failures', $results);

        self::assertGreaterThan(0, $results['total'], 'The frontend suite must contain tests.');

        $failureReport = $this->formatFailures($results['failures']);
        self::assertSame(
            0,
            $results['failed'],
            sprintf(
                '%d of %d frontend tests failed.%s',
                $results['failed'],
                $results['total'],
                $failureReport,
            ),
        );
        self::assertSame(
            $results['total'],
            $results['passed'],
            sprintf('Passed/passed+failed mismatch: %s', $failureReport),
        );
    }

    private function findChromiumBinary(): ?string
    {
        $envBinary = getenv('CHROMIUM_BINARY');
        if (is_string($envBinary) && $envBinary !== '' && is_executable($envBinary)) {
            return $envBinary;
        }
        $located = trim((string) shell_exec('command -v chromium 2>/dev/null'));
        return $located !== '' && is_executable($located) ? $located : null;
    }

    private function formatFailures(array $failures): string
    {
        if ($failures === []) {
            return '';
        }
        $lines = ["\nFrontend test failures:"];
        foreach ($failures as $failure) {
            $lines[] = sprintf('- %s: %s', $failure['name'] ?? 'unknown', $failure['message'] ?? 'no message');
        }
        return implode("\n", $lines);
    }
}
