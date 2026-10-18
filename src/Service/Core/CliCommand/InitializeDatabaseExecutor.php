<?php

declare(strict_types=1);

namespace SmolCms\Service\Core\CliCommand;

use PDO;
use RuntimeException;
use SmolCms\Data\Constant\CliCommandFlag;
use SmolCms\Service\File\FileSystemAccessService;

final readonly class InitializeDatabaseExecutor implements CliCommandExecutor
{
    public function __construct(
        private PDO                     $pdo,
        private FileSystemAccessService $fileSystemAccessService,
    )
    {
    }

    public function handles(): CliCommandFlag
    {
        return CliCommandFlag::INIT_DB;
    }

    public function helptext(): string
    {
        return 'Initialize an empty database; pass --reset to drop and recreate existing tables.';
    }

    public function execute(array $arguments, bool $noconfirm = false): void
    {
        $reset = in_array('--reset', $arguments, true);
        $driver = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        $tableNames = $this->getTableNames($driver);

        if (!$reset && $tableNames !== []) {
            echo "Database is already initialized; no changes made.\n";
            return;
        }

        $initFile = match ($driver) {
            'sqlite' => ROOT_DIR . '/private/init/init_sqlite.sql',
            'mysql' => ROOT_DIR . '/private/init/init.sql',
            default => throw new RuntimeException("Unsupported database driver: $driver"),
        };
        $sql = $this->fileSystemAccessService->readFile($initFile);

        if ($reset && !$noconfirm) {
            echo "This will drop all tables and reinitialize the database. Proceed? [y/N] ";
            $input = fgets(STDIN);
            if (!is_string($input) || strtolower(trim($input)) !== 'y') {
                echo "Aborted.\n";
                return;
            }
        }

        if ($reset && $tableNames === []) {
            echo "No tables to drop.\n";
        }

        if ($reset) {
            $this->dropTables($driver, $tableNames);
        }

        $this->pdo->exec($sql);
        echo "Initialized database from: $initFile\n";
        echo $reset ? "Reset complete.\n" : "Initialization complete.\n";
    }

    private function getTableNames(string $driver): array
    {
        return match ($driver) {
            'sqlite' => $this->getSqliteTableNames(),
            'mysql' => $this->getMySqlTableNames(),
            default => throw new RuntimeException("Unsupported database driver: $driver"),
        };
    }

    private function dropTables(string $driver, array $tableNames): void
    {
        if ($driver === 'mysql') {
            $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        }

        try {
            foreach ($tableNames as $tableName) {
                $this->pdo->exec("DROP TABLE IF EXISTS $tableName");
                echo "Dropped table: $tableName\n";
            }
        } finally {
            if ($driver === 'mysql') {
                $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
            }
        }
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
