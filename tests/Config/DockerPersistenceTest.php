<?php

declare(strict_types=1);

namespace SmolCms\Test\Config;

use PHPUnit\Framework\TestCase;

class DockerPersistenceTest extends TestCase
{
    public function testDatabaseDirectoryIsBindMountedFromTheHostStateDirectory(): void
    {
        $compose = file_get_contents(dirname(__DIR__, 2) . '/docker/compose.yaml');
        self::assertIsString($compose);

        self::assertMatchesRegularExpression(
            '/source:\s*"\$\{HOME:\?HOME must be set\}\/SmolCms\/db"\s*\n\s*target:\s*\/var\/www\/html\/private\/db/',
            $compose,
        );
    }

    public function testEntrypointInitializesWithoutResettingTheDatabase(): void
    {
        $entrypoint = file_get_contents(dirname(__DIR__, 2) . '/docker/container-entrypoint.sh');
        self::assertIsString($entrypoint);

        self::assertStringContainsString('--init-db', $entrypoint);
        self::assertStringNotContainsString('--reset', $entrypoint);
    }
}
