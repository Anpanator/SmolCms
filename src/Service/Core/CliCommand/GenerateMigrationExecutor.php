<?php

declare(strict_types=1);

namespace SmolCms\Service\Core\CliCommand;

use SmolCms\Data\Constant\CliCommandFlag;
use SmolCms\Exception\FileAccessException;
use SmolCms\Service\File\FileSystemAccessService;

final readonly class GenerateMigrationExecutor implements CliCommandExecutor
{
    private const string MIGRATION_DIR = ROOT_DIR . '/private/sql';

    public function __construct(
        private FileSystemAccessService $fileSystemAccessService,
    )
    {
    }

    public function handles(): CliCommandFlag
    {
        return CliCommandFlag::GENERATE_MIGRATION;
    }

    public function execute(array $arguments, bool $noconfirm = false): void
    {
        $description = $arguments[0] ?? '';
        if ($description === '') {
            echo "Usage: --generate-migration \"<description>\"\n";
            echo "The description will be used as part of the migration filename.\n";
            return;
        }

        $timestamp = date('Y-m-d_H-i-s');
        $safeDescription = preg_replace('/[^a-zA-Z0-9_-]/', '_', $description);
        $safeDescription = trim($safeDescription, '_');
        if ($safeDescription === '') {
            $safeDescription = 'migration';
        }
        $filename = $timestamp . '__' . $safeDescription . '.sql';
        $filePath = self::MIGRATION_DIR . '/' . $filename;

        try {
            $this->fileSystemAccessService->writeFile($filePath, "-- Migration: $description\n");
            echo "Created migration: $filename\n";
        } catch (FileAccessException $e) {
            echo "Failed to create migration file: " . $e->getMessage() . "\n";
        }
    }
}
