<?php
declare(strict_types=1);

namespace SmolCms\Test\Service\Core\CliCommand;

use PDO;
use SmolCms\Data\Constant\CliCommandFlag;
use SmolCms\Service\Core\CliCommand\MigrationsExecutor;
use SmolCms\TestUtils\Attributes\Autowire;
use SmolCms\TestUtils\FunctionalTestCase;

class MigrationsExecutorTest extends FunctionalTestCase
{
    #[Autowire]
    private MigrationsExecutor $migrationsExecutor;

    #[Autowire]
    private PDO $pdo;

    protected function tearDown(): void
    {
        $testFile = ROOT_DIR . '/private/sql/test_migration.sql';
        if (file_exists($testFile)) {
            unlink($testFile);
        }
        parent::tearDown();
    }

    public function testHandles_returnsMigrations(): void
    {
        $this->assertSame(CliCommandFlag::MIGRATIONS, $this->migrationsExecutor->handles());
    }

    public function testExecute_runsPendingMigrationAndRecordsIt(): void
    {
        $testFile = ROOT_DIR . '/private/sql/test_migration.sql';
        file_put_contents($testFile, 'CREATE TABLE migration_test_table (id INTEGER PRIMARY KEY);');

        ob_start();
        $this->migrationsExecutor->execute([], true);
        ob_end_clean();

        $stmt = $this->pdo->query('SELECT filename FROM migration');
        $executed = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $this->assertContains('test_migration.sql', $executed);

        $tables = $this->pdo->query(
            "SELECT name FROM sqlite_master WHERE type='table' AND name='migration_test_table'"
        )->fetchAll(PDO::FETCH_COLUMN);
        $this->assertContains('migration_test_table', $tables);
    }

    public function testExecute_doesNotRerunAlreadyExecutedMigration(): void
    {
        $testFile = ROOT_DIR . '/private/sql/test_migration.sql';
        file_put_contents($testFile, 'CREATE TABLE migration_test_dup (id INTEGER PRIMARY KEY);');

        ob_start();
        $this->migrationsExecutor->execute([], true);
        ob_end_clean();

        ob_start();
        $this->migrationsExecutor->execute([], true);
        $output = ob_get_clean();

        $this->assertStringContainsString('All migrations have already been executed.', $output);

        $stmt = $this->pdo->query('SELECT COUNT(*) FROM migration');
        $count = (int)$stmt->fetchColumn();
        $this->assertEquals(1, $count);
    }

    public function testExecute_noMigrationFiles_outputsMessage(): void
    {
        $this->expectOutputString("No migration files found.\n");
        $this->migrationsExecutor->execute([], true);
    }

    public function testExecute_skipsEmptyMigrationFile(): void
    {
        $testFile = ROOT_DIR . '/private/sql/test_empty.sql';
        file_put_contents($testFile, '');

        ob_start();
        $this->migrationsExecutor->execute([], true);
        $output = ob_get_clean();

        $this->assertStringContainsString('Skipping empty migration: test_empty.sql', $output);

        if (file_exists($testFile)) {
            unlink($testFile);
        }
    }
}
