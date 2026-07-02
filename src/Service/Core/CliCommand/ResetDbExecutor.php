<?php

declare(strict_types=1);

namespace SmolCms\Service\Core\CliCommand;

use PDO;
use RuntimeException;
use SmolCms\Data\Constant\CliCommandFlag;

final readonly class ResetDbExecutor implements CliCommandExecutor
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function handles(): CliCommandFlag
    {
        return CliCommandFlag::RESET_DB;
    }

    public function execute(array $arguments, bool $noconfirm = false): void
    {
        $driver = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        $tableNames = match ($driver) {
            'sqlite' => $this->getSqliteTableNames(),
            'mysql' => $this->getMySqlTableNames(),
            default => throw new RuntimeException("Unsupported database driver: $driver"),
        };

        if ($tableNames === []) {
            echo "No tables to drop.\n";
            return;
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
