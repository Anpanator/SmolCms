<?php

declare(strict_types=1);

namespace SmolCms\Service\Core\CliCommand;

use Exception;
use PDO;
use SmolCms\Data\Constant\CliCommandFlag;
use SmolCms\Exception\FileAccessException;
use SmolCms\Service\File\FileSystemAccessService;

final readonly class MigrationsExecutor implements CliCommandExecutor
{
    public function __construct(
        private string $migrationDir,
        private PDO    $pdo,
        private FileSystemAccessService $fileSystemAccessService,
    )
    {
    }

    public function handles(): CliCommandFlag
    {
        return CliCommandFlag::MIGRATIONS;
    }

    public function helptext(): string
    {
        return 'Execute pending database migration files from ' . $this->migrationDir;
    }

    public function execute(array $arguments, bool $noconfirm = false): void
    {
        try {
            $migrationFiles = $this->fileSystemAccessService->listFiles($this->migrationDir);
        } catch (FileAccessException) {
            echo "Migration directory not found: {${$this->migrationDir}}\n";
            return;
        }

        $files = [];
        foreach ($migrationFiles as $file) {
            if (str_ends_with($file, '.sql')) {
                $files[] = $this->migrationDir . '/' . $file;
            }
        }
        if ($files === []) {
            echo "No migration files found.\n";
            return;
        }

        sort($files);

        $executed = $this->getExecutedMigrations();
        $pending = [];

        foreach ($files as $file) {
            $filename = basename($file);
            if (!isset($executed[$filename])) {
                $pending[] = $file;
            }
        }

        if ($pending === []) {
            echo "All migrations have already been executed.\n";
            return;
        }

        if (!$noconfirm) {
            echo "The following migrations will be executed:\n";
            foreach ($pending as $file) {
                echo '  ' . basename($file) . "\n";
            }
            echo "Proceed? [y/N] ";
            $input = trim(fgets(STDIN));
            if (strtolower($input) !== 'y') {
                echo "Aborted.\n";
                return;
            }
        }

        foreach ($pending as $file) {
            $filename = basename($file);
            $sql = $this->fileSystemAccessService->readFile($file);
            if (trim($sql) === '') {
                echo "Skipping empty migration: $filename\n";
                continue;
            }

            try {
                $this->pdo->exec($sql);
                $this->recordMigration($filename);
                echo "Executed migration: $filename\n";
            } catch (Exception $e) {
                echo "Failed to execute migration $filename: " . $e->getMessage() . "\n";
                return;
            }
        }

        echo "Migrations complete.\n";
    }

    private function getExecutedMigrations(): array
    {
        $stmt = $this->pdo->query('SELECT filename FROM migration');
        $filenames = $stmt->fetchAll(PDO::FETCH_COLUMN);
        return array_flip($filenames);
    }

    private function recordMigration(string $filename): void
    {
        $stmt = $this->pdo->prepare('INSERT INTO migration (filename) VALUES (:filename)');
        $stmt->execute(['filename' => $filename]);
    }
}
