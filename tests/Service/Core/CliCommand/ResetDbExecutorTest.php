<?php

declare(strict_types=1);

namespace SmolCms\Test\Service\Core\CliCommand;

use PDO;
use RuntimeException;
use SmolCms\Data\Constant\CliCommandFlag;
use SmolCms\Service\Core\CliCommand\ResetDbExecutor;
use SmolCms\TestUtils\Attributes\Autowire;
use SmolCms\TestUtils\FunctionalTestCase;

class ResetDbExecutorTest extends FunctionalTestCase
{
    #[Autowire]
    private ResetDbExecutor $resetDbExecutor;

    #[Autowire]
    private PDO $pdo;

    /** @var list<string> */
    private array $temporaryFiles = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->restoreSchema();
    }

    protected function tearDown(): void
    {
        foreach ($this->temporaryFiles as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }
        parent::tearDown();
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

    public function testHandles_returnsResetDb(): void
    {
        $this->assertSame(CliCommandFlag::RESET_DB, $this->resetDbExecutor->handles());
    }

    public function testExecute_dropsTablesAndReinitializes(): void
    {
        $tablesBefore = $this->pdo->query(
            "SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'"
        )->fetchAll(PDO::FETCH_COLUMN);
        $this->assertGreaterThan(0, $tablesBefore);
        ob_start();
        $this->resetDbExecutor->execute([], true);
        ob_end_clean();

        $tablesAfter = $this->pdo->query(
            "SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'"
        )->fetchAll(PDO::FETCH_COLUMN);
        $this->assertGreaterThan(0, $tablesAfter);
    }

    public function testExecute_noTablesToDrop_outputsMessage(): void
    {
        ob_start();
        $this->resetDbExecutor->execute([], true);
        ob_end_clean();

        $allTables = $this->pdo->query(
            "SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'"
        )->fetchAll(PDO::FETCH_COLUMN);
        foreach ($allTables as $tableName) {
            $this->pdo->exec("DROP TABLE IF EXISTS $tableName");
        }

        $expectedOutput = "No tables to drop.\n"
            . "Initialized database from: " . ROOT_DIR . "/private/init/init_sqlite.sql\n"
            . "Reset complete.\n";
        $this->expectOutputString($expectedOutput);
        $this->resetDbExecutor->execute([], true);
    }
}
