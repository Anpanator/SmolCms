<?php

declare(strict_types=1);

namespace SmolCms\Test\Config;

use PHPUnit\Framework\TestCase;

class ContainerEntrypointTest extends TestCase
{
    public function testImageWorkerRunsEverySecond(): void
    {
        $scriptPath = dirname(__DIR__, 2) . '/docker/container-entrypoint.sh';
        $script = file_get_contents($scriptPath);
        self::assertIsString($script);
        self::assertMatchesRegularExpression('/--process-images 100[^\n]*\n\s*sleep 1\b/', $script);
        self::assertStringNotContainsString('sleep 60', $script);
    }
}
