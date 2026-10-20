<?php

declare(strict_types=1);

namespace SmolCms\Test\Service\Core\CliCommand;

use PDO;
use PDOStatement;
use PHPUnit\Framework\MockObject\MockObject;
use SmolCms\Service\Core\CliCommand\InitializeDatabaseExecutor;
use SmolCms\Service\File\FileSystemAccessService;
use SmolCms\TestUtils\Attributes\Mock;
use SmolCms\TestUtils\SimpleTestCase;

class InitializeDatabaseExecutorMySqlTest extends SimpleTestCase
{
    #[Mock(PDO::class)]
    private PDO|MockObject $pdo;

    #[Mock(PDOStatement::class)]
    private PDOStatement|MockObject $statement;

    #[Mock(FileSystemAccessService::class)]
    private FileSystemAccessService|MockObject $fileSystemAccessService;

    private InitializeDatabaseExecutor $executor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->executor = new InitializeDatabaseExecutor($this->pdo, $this->fileSystemAccessService);
    }

    public function testExecuteDiscoversMySqlTablesAndTogglesForeignKeyChecks(): void
    {
        $executedSql = [];
        $this->pdo
            ->expects($this->once())
            ->method('getAttribute')
            ->with(PDO::ATTR_DRIVER_NAME)
            ->willReturn('mysql');
        $this->pdo
            ->expects($this->once())
            ->method('query')
            ->with('SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE()')
            ->willReturn($this->statement);
        $this->statement
            ->expects($this->once())
            ->method('fetchAll')
            ->with(PDO::FETCH_COLUMN)
            ->willReturn(['first_table', 'second_table'])
            ->seal();
        $this->fileSystemAccessService
            ->expects($this->once())
            ->method('readFile')
            ->with(ROOT_DIR . '/private/init/init.sql')
            ->willReturn('mysql schema')
            ->seal();
        $this->pdo
            ->expects($this->exactly(5))
            ->method('exec')
            ->willReturnCallback(static function (string $sql) use (&$executedSql): int {
                $executedSql[] = $sql;
                return 0;
            })
            ->seal();

        $this->expectOutputString(
            "Dropped table: first_table\n"
            . "Dropped table: second_table\n"
            . 'Initialized database from: ' . ROOT_DIR . "/private/init/init.sql\n"
            . "Reset complete.\n",
        );
        $this->executor->execute(['--reset'], true);

        self::assertSame(
            [
                'SET FOREIGN_KEY_CHECKS = 0',
                'DROP TABLE IF EXISTS first_table',
                'DROP TABLE IF EXISTS second_table',
                'SET FOREIGN_KEY_CHECKS = 1',
                'mysql schema',
            ],
            $executedSql,
        );
    }
}
