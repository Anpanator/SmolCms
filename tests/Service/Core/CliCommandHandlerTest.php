<?php
declare(strict_types=1);

namespace SmolCms\Test\Service\Core;

use PHPUnit\Framework\MockObject\MockObject;
use SmolCms\Data\Constant\CliCommandFlag;
use SmolCms\Service\Core\CliCommand\CliCommandExecutor;
use SmolCms\Service\Core\CliCommandHandler;
use SmolCms\Service\Core\ContextService;
use SmolCms\TestUtils\Attributes\Mock;
use SmolCms\TestUtils\Attributes\Stub;
use SmolCms\TestUtils\SimpleTestCase;

class CliCommandHandlerTest extends SimpleTestCase
{
    #[Mock(CliCommandExecutor::class)]
    private CliCommandExecutor|MockObject $generateMigrationExecutor;

    #[Mock(CliCommandExecutor::class)]
    private CliCommandExecutor|MockObject $migrationsExecutor;

    #[Mock(CliCommandExecutor::class)]
    private CliCommandExecutor|MockObject $resetDbExecutor;

    #[Stub(ContextService::class)]
    private ContextService|MockObject $contextService;

    private CliCommandHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();
        $GLOBALS['argv'] = ['script.php'];
        $this->contextService->method('isCliMode')->willReturn(true);
        $this->generateMigrationExecutor->method('handles')->willReturn(CliCommandFlag::GENERATE_MIGRATION);
        $this->generateMigrationExecutor->method('helptext')->willReturn('Generate a new migration file.');
        $this->migrationsExecutor->method('handles')->willReturn(CliCommandFlag::MIGRATIONS);
        $this->migrationsExecutor->method('helptext')->willReturn('Execute pending database migrations.');
        $this->resetDbExecutor->method('handles')->willReturn(CliCommandFlag::RESET_DB);
        $this->resetDbExecutor->method('helptext')->willReturn('Drop all tables and reinitialize the database.');
        $this->handler = new CliCommandHandler(
            $this->contextService,
            $this->generateMigrationExecutor,
            $this->migrationsExecutor,
            $this->resetDbExecutor,
        );
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['argv']);
        parent::tearDown();
    }

    public function testRunCommand_noFlags_outputsAvailableFlags(): void
    {
        $this->migrationsExecutor->expects($this->never())->method('execute')->seal();
        $this->resetDbExecutor->expects($this->never())->method('execute')->seal();
        $this->generateMigrationExecutor->expects($this->never())->method('execute')->seal();

        $this->expectOutputString(
            "Available commands:\n"
            . "  --generate-migration  Generate a new migration file.\n"
            . "  --migrations  Execute pending database migrations.\n"
            . "  --reset-db  Drop all tables and reinitialize the database.\n"
            . "  --noconfirm  Skip confirmation prompts.\n"
        );
        $this->handler->runCommand();
    }

    public function testRunCommand_migrationsFlag_callsExecutor(): void
    {
        $GLOBALS['argv'] = ['script.php', '--migrations'];

        $this->migrationsExecutor->expects($this->once())->method('execute')->with([], false)->seal();
        $this->resetDbExecutor->expects($this->never())->method('execute')->seal();
        $this->generateMigrationExecutor->expects($this->never())->method('execute')->seal();

        $this->handler->runCommand();
    }

    public function testRunCommand_noconfirmFlag_outputsDefaultMessage(): void
    {
        $GLOBALS['argv'] = ['script.php', '--noconfirm'];

        $this->migrationsExecutor->expects($this->never())->method('execute')->seal();
        $this->resetDbExecutor->expects($this->never())->method('execute')->seal();
        $this->generateMigrationExecutor->expects($this->never())->method('execute')->seal();

        $this->expectOutputString(
            "Available commands:\n"
            . "  --generate-migration  Generate a new migration file.\n"
            . "  --migrations  Execute pending database migrations.\n"
            . "  --reset-db  Drop all tables and reinitialize the database.\n"
            . "  --noconfirm  Skip confirmation prompts.\n"
        );
        $this->handler->runCommand();
    }

    public function testRunCommand_noconfirmWithMigrations_passesTrue(): void
    {
        $GLOBALS['argv'] = ['script.php', '--migrations', '--noconfirm'];

        $this->migrationsExecutor->expects($this->once())->method('execute')->with([], true)->seal();
        $this->resetDbExecutor->expects($this->never())->method('execute')->seal();
        $this->generateMigrationExecutor->expects($this->never())->method('execute')->seal();

        $this->handler->runCommand();
    }

    public function testRunCommand_multipleFlags_callsMultipleExecutors(): void
    {
        $GLOBALS['argv'] = ['script.php', '--migrations', '--reset-db'];

        $this->migrationsExecutor->expects($this->once())->method('execute')->seal();
        $this->resetDbExecutor->expects($this->once())->method('execute')->seal();
        $this->generateMigrationExecutor->expects($this->never())->method('execute')->seal();

        $this->handler->runCommand();
    }

    public function testRunCommand_flagWithArguments_passesArguments(): void
    {
        $GLOBALS['argv'] = ['script.php', '--migrations', 'arg1', 'arg2'];

        $this->migrationsExecutor->expects($this->once())->method('execute')->with(['arg1', 'arg2'], false)->seal();
        $this->resetDbExecutor->expects($this->never())->method('execute')->seal();
        $this->generateMigrationExecutor->expects($this->never())->method('execute')->seal();

        $this->handler->runCommand();
    }

    public function testRunCommand_missingArgv_noError(): void
    {
        unset($GLOBALS['argv']);

        $this->migrationsExecutor->expects($this->never())->method('execute')->seal();
        $this->resetDbExecutor->expects($this->never())->method('execute')->seal();
        $this->generateMigrationExecutor->expects($this->never())->method('execute')->seal();

        $this->expectOutputString(
            "Available commands:\n"
            . "  --generate-migration  Generate a new migration file.\n"
            . "  --migrations  Execute pending database migrations.\n"
            . "  --reset-db  Drop all tables and reinitialize the database.\n"
            . "  --noconfirm  Skip confirmation prompts.\n"
        );
        $this->handler->runCommand();
    }

    public function testRunCommand_nonFlagTokensBeforeFirstFlag_ignored(): void
    {
        $GLOBALS['argv'] = ['script.php', 'some', 'junk', '--migrations'];

        $this->migrationsExecutor->expects($this->once())->method('execute')->with([], false)->seal();
        $this->resetDbExecutor->expects($this->never())->method('execute')->seal();
        $this->generateMigrationExecutor->expects($this->never())->method('execute')->seal();

        $this->handler->runCommand();
    }

    public function testRunCommand_notCliMode_returnsFalse(): void
    {
        $this->contextService = $this->createStub(ContextService::class);
        $this->contextService->method('isCliMode')->willReturn(false);

        $this->handler = new CliCommandHandler(
            $this->contextService,
            $this->generateMigrationExecutor,
            $this->migrationsExecutor,
            $this->resetDbExecutor,
        );

        $this->migrationsExecutor->expects($this->never())->method('execute')->seal();
        $this->resetDbExecutor->expects($this->never())->method('execute')->seal();
        $this->generateMigrationExecutor->expects($this->never())->method('execute')->seal();


        self::assertFalse($this->handler->runCommand());
    }

    public function testRunCommand_resetDbFlag_callsExecutor(): void
    {
        $GLOBALS['argv'] = ['script.php', '--reset-db'];

        $this->migrationsExecutor->expects($this->never())->method('execute')->seal();
        $this->resetDbExecutor->expects($this->once())->method('execute')->with([], false)->seal();
        $this->generateMigrationExecutor->expects($this->never())->method('execute')->seal();

        $this->handler->runCommand();
    }

    public function testRunCommand_generateMigrationFlag_callsExecutor(): void
    {
        $GLOBALS['argv'] = ['script.php', '--generate-migration'];

        $this->migrationsExecutor->expects($this->never())->method('execute')->seal();
        $this->resetDbExecutor->expects($this->never())->method('execute')->seal();
        $this->generateMigrationExecutor->expects($this->once())->method('execute')->seal();

        $this->expectOutputString('');
        $this->handler->runCommand();
    }

    public function testRunCommand_noconfirmWithResetDb_passesTrue(): void
    {
        $GLOBALS['argv'] = ['script.php', '--reset-db', '--noconfirm'];

        $this->migrationsExecutor->expects($this->never())->method('execute')->seal();
        $this->resetDbExecutor->expects($this->once())->method('execute')->with([], true)->seal();
        $this->generateMigrationExecutor->expects($this->never())->method('execute')->seal();

        $this->handler->runCommand();
    }
}
