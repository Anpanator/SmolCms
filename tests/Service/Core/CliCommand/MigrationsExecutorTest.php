<?php

declare(strict_types=1);

namespace SmolCms\Test\Service\Core\CliCommand;

use PDO;
use RuntimeException;
use SmolCms\Data\Constant\CliCommandFlag;
use SmolCms\Service\Core\CliCommand\MigrationsExecutor;
use SmolCms\Service\File\FileSystemAccessService;
use SmolCms\TestUtils\Attributes\Autowire;
use SmolCms\TestUtils\FunctionalTestCase;

class MigrationsExecutorTest extends FunctionalTestCase
{
    #[Autowire]
    private PDO $pdo;

    #[Autowire]
    private FileSystemAccessService $fileSystemAccessService;

    private MigrationsExecutor $testee;
    private string $migrationDir;
    private string $testToken;
    /** @var list<string> */
    private array $migrationFiles = [];
    /** @var list<string> */
    private array $migrationRows = [];
    /** @var list<string> */
    private array $migrationTables = [];
    /** @var list<string> */
    private array $temporaryFiles = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->testToken = bin2hex(random_bytes(8));
        $this->migrationDir = ROOT_DIR . '/private/sql/cli-migrations-' . $this->testToken;
        if (!mkdir($this->migrationDir, 0777, true)) {
            throw new RuntimeException("Could not create test migration directory: {$this->migrationDir}");
        }
        $this->testee = new MigrationsExecutor(
            $this->migrationDir,
            $this->pdo,
            $this->fileSystemAccessService
        );
    }

    protected function tearDown(): void
    {
        $statement = $this->pdo->prepare('DELETE FROM migration WHERE filename = :filename');
        foreach ($this->migrationRows as $filename) {
            $statement->execute(['filename' => $filename]);
        }

        foreach ($this->migrationTables as $tableName) {
            $this->pdo->exec("DROP TABLE IF EXISTS $tableName");
        }

        foreach ($this->migrationFiles as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }
        foreach ($this->temporaryFiles as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }
        if (is_dir($this->migrationDir)) {
            rmdir($this->migrationDir);
        }

        parent::tearDown();
    }

    private function createMigration(string $filename, string $sql): void
    {
        $path = $this->migrationDir . '/' . $filename;
        $this->migrationFiles[] = $path;
        $this->migrationRows[] = $filename;
        file_put_contents($path, $sql);
    }

    private function addTableMigration(string $filename, string $tableName): void
    {
        $this->migrationTables[] = $tableName;
        $this->createMigration($filename, "CREATE TABLE $tableName (id INTEGER PRIMARY KEY);");
    }

    public function testHandles_returnsMigrations(): void
    {
        $this->assertSame(CliCommandFlag::MIGRATIONS, $this->testee->handles());
    }

    public function testExecute_runsPendingMigrationAndRecordsIt(): void
    {
        $filename = '001_' . $this->testToken . '_single.sql';
        $tableName = 'migration_test_' . $this->testToken . '_single';
        $this->addTableMigration($filename, $tableName);

        ob_start();
        $this->testee->execute([], true);
        ob_end_clean();

        $stmt = $this->pdo->prepare('SELECT filename FROM migration WHERE filename = :filename');
        $stmt->execute(['filename' => $filename]);
        $executed = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $this->assertSame([$filename], $executed);

        $tables = $this->pdo->query(
            "SELECT name FROM sqlite_master WHERE type='table' AND name='$tableName'"
        )->fetchAll(PDO::FETCH_COLUMN);
        $this->assertSame([$tableName], $tables);
    }

    public function testExecute_doesNotRerunAlreadyExecutedMigration(): void
    {
        $filename = '001_' . $this->testToken . '_duplicate.sql';
        $tableName = 'migration_test_' . $this->testToken . '_duplicate';
        $this->addTableMigration($filename, $tableName);

        ob_start();
        $this->testee->execute([], true);
        ob_end_clean();

        ob_start();
        $this->testee->execute([], true);
        $output = ob_get_clean();

        $this->assertStringContainsString('All migrations have already been executed.', $output);

        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM migration WHERE filename = :filename');
        $stmt->execute(['filename' => $filename]);
        $count = (int)$stmt->fetchColumn();
        $this->assertSame(1, $count);
    }

    public function testExecute_noMigrationFiles_outputsMessage(): void
    {
        $this->expectOutputString("No migration files found.\n");
        $this->testee->execute([], true);
    }

    public function testExecute_skipsEmptyMigrationFile(): void
    {
        $filename = '001_' . $this->testToken . '_empty.sql';
        $this->createMigration($filename, '');

        ob_start();
        $this->testee->execute([], true);
        $output = ob_get_clean();

        self::assertIsString($output);
        $this->assertStringContainsString("Skipping empty migration: $filename", $output);
    }

    public function testExecute_runsMultiplePendingMigrationsInFilenameOrder(): void
    {
        $laterFilename = '002_' . $this->testToken . '_later.sql';
        $earlierFilename = '001_' . $this->testToken . '_earlier.sql';
        $laterTable = 'migration_test_' . $this->testToken . '_later';
        $earlierTable = 'migration_test_' . $this->testToken . '_earlier';
        $this->addTableMigration($laterFilename, $laterTable);
        $this->addTableMigration($earlierFilename, $earlierTable);

        ob_start();
        $this->testee->execute([], true);
        $output = ob_get_clean();

        self::assertIsString($output);
        $earlierPosition = strpos($output, "Executed migration: $earlierFilename");
        $laterPosition = strpos($output, "Executed migration: $laterFilename");
        self::assertIsInt($earlierPosition);
        self::assertIsInt($laterPosition);
        self::assertLessThan($laterPosition, $earlierPosition);

        $stmt = $this->pdo->query(
            "SELECT filename FROM migration WHERE filename IN ('$earlierFilename', '$laterFilename') ORDER BY rowid"
        );
        self::assertSame([$earlierFilename, $laterFilename], $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    public function testExecute_failedMigrationIsNotRecorded(): void
    {
        $filename = '001_' . $this->testToken . '_failed.sql';
        $this->createMigration($filename, 'THIS IS NOT VALID SQL;');

        ob_start();
        $this->testee->execute([], true);
        $output = ob_get_clean();

        self::assertIsString($output);
        self::assertStringContainsString("Failed to execute migration $filename:", $output);

        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM migration WHERE filename = :filename');
        $stmt->execute(['filename' => $filename]);
        self::assertSame(0, (int)$stmt->fetchColumn());
    }

    public function testExecute_missingMigrationDirectory_outputsError(): void
    {
        $missingDirectory = $this->migrationDir . '/missing';
        $executor = new MigrationsExecutor($missingDirectory, $this->pdo, $this->fileSystemAccessService);

        $this->expectOutputString("Migration directory not found: $missingDirectory\n");
        $executor->execute([], true);
    }
}
