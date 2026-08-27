<?php

declare(strict_types=1);

namespace SmolCms\Service\Core\CliCommand;

use PDO;
use RuntimeException;
use SmolCms\Data\Constant\CliCommandFlag;
use SmolCms\Service\File\FileSystemAccessService;

final readonly class ResetDbExecutor implements CliCommandExecutor
{
    private PDO $pdo;
    private FileSystemAccessService $fileSystemAccessService;

    public function __construct(PDO $pdo, FileSystemAccessService $fileSystemAccessService)
    {
        $this->pdo = $pdo;
        $this->fileSystemAccessService = $fileSystemAccessService;
    }

    public function handles(): CliCommandFlag
    {
        return CliCommandFlag::RESET_DB;
    }

    public function helptext(): string
    {
        return 'Drop all tables and reinitialize the database from the schema file.';
    }

    public function execute(array $arguments, bool $noconfirm = false): void
    {
        $driver = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        $tableNames = match ($driver) {
            'sqlite' => $this->getSqliteTableNames(),
            'mysql' => $this->getMySqlTableNames(),
            default => throw new RuntimeException("Unsupported database driver: $driver"),
        };

        if (!$noconfirm) {
            echo "This will drop all tables and reinitialize the database. Proceed? [y/N] ";
            $input = fgets(STDIN);
            if (!is_string($input) || strtolower(trim($input)) !== 'y') {
                echo "Aborted.\n";
                return;
            }
        }

        if ($tableNames === []) {
            echo "No tables to drop.\n";
        }

        if ($driver === 'mysql') {
            $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        }

        foreach ($tableNames as $tableName) {
            $this->pdo->exec("DROP TABLE IF EXISTS $tableName");
            echo "Dropped table: $tableName\n";
        }

        if ($driver === 'mysql') {
            $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
        }

        $initFile = match ($driver) {
            'sqlite' => ROOT_DIR . '/private/init/init_sqlite.sql',
            'mysql' => ROOT_DIR . '/private/init/init.sql',
            default => throw new RuntimeException("Unsupported database driver: $driver"),
        };

        $sql = $this->fileSystemAccessService->readFile($initFile);

        $this->pdo->exec($sql);
        echo "Initialized database from: $initFile\n";

        echo "Reset complete.\n";
    }

    private function getSqliteTableNames(): array
    {
        $stmt = $this->pdo->query(
            "SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%'"
        );
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    private function getMySqlTableNames(): array
    {
        $stmt = $this->pdo->query(
            'SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE()'
        );
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
}
