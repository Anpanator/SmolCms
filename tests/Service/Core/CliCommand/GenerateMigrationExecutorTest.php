<?php

declare(strict_types=1);

namespace SmolCms\Test\Service\Core\CliCommand;

use PHPUnit\Framework\MockObject\MockObject;
use SmolCms\Exception\FileAccessException;
use SmolCms\Service\Core\CliCommand\GenerateMigrationExecutor;
use SmolCms\Service\File\FileSystemAccessService;
use SmolCms\TestUtils\Attributes\Mock;
use SmolCms\TestUtils\SimpleTestCase;

class GenerateMigrationExecutorTest extends SimpleTestCase
{
    #[Mock(FileSystemAccessService::class)]
    private FileSystemAccessService|MockObject $fileSystemAccessService;

    private GenerateMigrationExecutor $generateMigrationExecutor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->generateMigrationExecutor = new GenerateMigrationExecutor($this->fileSystemAccessService);
    }

    public function testExecute_withoutDescription_outputsUsage(): void
    {
        $this->fileSystemAccessService
            ->expects($this->never())
            ->method('writeFile')
            ->seal();

        $this->expectOutputString(
            "Usage: --generate-migration \"<description>\"\n"
            . "The description will be used as part of the migration filename.\n"
        );
        $this->generateMigrationExecutor->execute([]);
    }

    public function testExecute_withSafeDescription_generatesMigrationFilename(): void
    {
        $this->fileSystemAccessService
            ->expects($this->once())
            ->method('writeFile')
            ->with(
                $this->callback(
                    fn(string $path): bool => preg_match(
                            '#^' . preg_quote(ROOT_DIR . '/private/sql/', '#')
                            . '\\d{4}-\\d{2}-\\d{2}_\\d{2}-\\d{2}-\\d{2}__add-users-table\\.sql$#',
                            $path
                        ) === 1
                ),
                "-- Migration: add-users-table\n"
            )
            ->seal();

        ob_start();
        $this->generateMigrationExecutor->execute(['add-users-table']);
        $output = ob_get_clean();

        self::assertIsString($output);
        self::assertMatchesRegularExpression(
            '/^Created migration: \d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2}__add-users-table\.sql\n$/',
            $output
        );
    }

    public function testExecute_withUnsafeDescription_sanitizesFilename(): void
    {
        $description = 'new users/v2';

        $this->fileSystemAccessService
            ->expects($this->once())
            ->method('writeFile')
            ->with(
                $this->callback(
                    fn(string $path): bool => preg_match(
                            '#^' . preg_quote(ROOT_DIR . '/private/sql/', '#')
                            . '\\d{4}-\\d{2}-\\d{2}_\\d{2}-\\d{2}-\\d{2}__new_users_v2\\.sql$#',
                            $path
                        ) === 1
                ),
                "-- Migration: $description\n"
            )
            ->seal();

        ob_start();
        $this->generateMigrationExecutor->execute([$description]);
        $output = ob_get_clean();

        self::assertIsString($output);
        self::assertMatchesRegularExpression(
            '/^Created migration: \d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2}__new_users_v2\.sql\n$/',
            $output
        );
    }

    public function testExecute_withOnlyUnsafeDescription_usesFallbackFilename(): void
    {
        $description = '/!@#$';

        $this->fileSystemAccessService
            ->expects($this->once())
            ->method('writeFile')
            ->with(
                $this->callback(
                    fn(string $path): bool => preg_match(
                            '#^' . preg_quote(ROOT_DIR . '/private/sql/', '#')
                            . '\\d{4}-\\d{2}-\\d{2}_\\d{2}-\\d{2}-\\d{2}__migration\\.sql$#',
                            $path
                        ) === 1
                ),
                "-- Migration: $description\n"
            )
            ->seal();

        ob_start();
        $this->generateMigrationExecutor->execute([$description]);
        $output = ob_get_clean();

        self::assertIsString($output);
        self::assertMatchesRegularExpression(
            '/^Created migration: \d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2}__migration\.sql\n$/',
            $output
        );
    }

    public function testExecute_whenFileAccessFails_outputsFailure(): void
    {
        $this->fileSystemAccessService
            ->expects($this->once())
            ->method('writeFile')
            ->willThrowException(new FileAccessException('write failed'))
            ->seal();

        $this->expectOutputString("Failed to create migration file: write failed\n");
        $this->generateMigrationExecutor->execute(['valid-description']);
    }
}
