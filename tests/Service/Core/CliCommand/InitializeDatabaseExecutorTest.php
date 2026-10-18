<?php

declare(strict_types=1);

namespace SmolCms\Test\Service\Core\CliCommand;

use PDO;
use RuntimeException;
use SmolCms\Data\Constant\CliCommandFlag;
use SmolCms\Service\Core\CliCommand\InitializeDatabaseExecutor;
use SmolCms\TestUtils\Attributes\Autowire;
use SmolCms\TestUtils\FunctionalTestCase;

class InitializeDatabaseExecutorTest extends FunctionalTestCase
{
    #[Autowire]
    private InitializeDatabaseExecutor $initializeDatabaseExecutor;

    #[Autowire]
    private PDO $pdo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dropTables();
        $this->restoreSchema();
    }

    private function restoreSchema(): void
    {
        $sql = file_get_contents(ROOT_DIR . '/private/init/init_sqlite.sql');
        if (!is_string($sql)) {
            throw new RuntimeException('Could not read SQLite test schema.');
        }
        $sql = str_replace('CREATE TABLE ', 'CREATE TABLE IF NOT EXISTS ', $sql);
        $this->pdo->exec($sql);
    }

    private function dropTables(): void
    {
        $tableNames = $this->pdo->query(
            "SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'"
        )->fetchAll(PDO::FETCH_COLUMN);
        foreach ($tableNames as $tableName) {
            $this->pdo->exec("DROP TABLE IF EXISTS $tableName");
        }
    }

    public function testHandles_returnsInitDb(): void
    {
        self::assertSame(CliCommandFlag::INIT_DB, $this->initializeDatabaseExecutor->handles());
    }

    public function testExecute_existingDatabaseDoesNotResetByDefault(): void
    {
        $this->pdo->exec("INSERT INTO migration (filename) VALUES ('preserved.sql')");

        ob_start();
        $this->initializeDatabaseExecutor->execute([]);
        $output = ob_get_clean();

        self::assertSame("Database is already initialized; no changes made.\n", $output);
        self::assertSame(
            'preserved.sql',
            $this->pdo->query('SELECT filename FROM migration')->fetchColumn(),
        );
    }

    public function testExecute_emptyDatabaseInitializesWithoutResetOrConfirmation(): void
    {
        $this->dropTables();

        ob_start();
        $this->initializeDatabaseExecutor->execute([]);
        $output = ob_get_clean();

        self::assertSame(
            'Initialized database from: ' . ROOT_DIR . "/private/init/init_sqlite.sql\n"
            . "Initialization complete.\n",
            $output,
        );
        $tables = $this->pdo->query(
            "SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'"
        )->fetchAll(PDO::FETCH_COLUMN);
        self::assertNotEmpty($tables);
    }

    public function testExecute_resetArgumentDropsTablesAndReinitializes(): void
    {
        $this->pdo->exec("INSERT INTO migration (filename) VALUES ('removed.sql')");

        ob_start();
        $this->initializeDatabaseExecutor->execute(['--reset'], true);
        $output = ob_get_clean();

        self::assertStringContainsString("Dropped table: migration\n", $output);
        self::assertStringContainsString("Reset complete.\n", $output);
        self::assertGreaterThan(
            0,
            $this->pdo->query(
                "SELECT COUNT(*) FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'"
            )->fetchColumn(),
        );
        self::assertSame(0, (int)$this->pdo->query('SELECT COUNT(*) FROM migration')->fetchColumn());
    }

    public function testExecute_resetOfEmptyDatabase_reportsNoTablesAndInitializes(): void
    {
        $this->dropTables();

        ob_start();
        $this->initializeDatabaseExecutor->execute(['--reset'], true);
        $output = ob_get_clean();

        self::assertSame(
            "No tables to drop.\n"
            . 'Initialized database from: ' . ROOT_DIR . "/private/init/init_sqlite.sql\n"
            . "Reset complete.\n",
            $output,
        );
    }
}
