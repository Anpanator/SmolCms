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
    #[Stub(CliCommandExecutor::class)]
    private CliCommandExecutor|MockObject $generateMigrationExecutor;

    #[Mock(CliCommandExecutor::class)]
    private CliCommandExecutor|MockObject $migrationsExecutor;

    #[Mock(CliCommandExecutor::class)]
    private CliCommandExecutor|MockObject $resetDbExecutor;

    private CliCommandHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();
        $GLOBALS['argv'] = ['script.php'];
        $this->generateMigrationExecutor->method('handles')->willReturn(CliCommandFlag::GENERATE_MIGRATION);
        $this->generateMigrationExecutor->method('helptext')->willReturn('Generate a new migration file.');
        $this->migrationsExecutor->method('handles')->willReturn(CliCommandFlag::MIGRATIONS);
        $this->migrationsExecutor->method('helptext')->willReturn('Execute pending database migrations.');
        $this->resetDbExecutor->method('handles')->willReturn(CliCommandFlag::RESET_DB);
        $this->resetDbExecutor->method('helptext')->willReturn('Drop all tables and reinitialize the database.');
        $this->handler = new CliCommandHandler(
            new ContextService(),
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

        $this->handler->runCommand();
    }

    public function testRunCommand_noconfirmFlag_outputsDefaultMessage(): void
    {
        $GLOBALS['argv'] = ['script.php', '--noconfirm'];

        $this->migrationsExecutor->expects($this->never())->method('execute')->seal();
        $this->resetDbExecutor->expects($this->never())->method('execute')->seal();

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

        $this->handler->runCommand();
    }

    public function testRunCommand_multipleFlags_callsAllExecutors(): void
    {
        $GLOBALS['argv'] = ['script.php', '--migrations', '--reset-db'];

        $this->migrationsExecutor->expects($this->once())->method('execute')->seal();
        $this->resetDbExecutor->expects($this->once())->method('execute')->seal();

        $this->handler->runCommand();
    }

    public function testRunCommand_flagWithArguments_passesArguments(): void
    {
        $GLOBALS['argv'] = ['script.php', '--migrations', 'arg1', 'arg2'];

        $this->migrationsExecutor->expects($this->once())->method('execute')->with(['arg1', 'arg2'], false)->seal();
        $this->resetDbExecutor->expects($this->never())->method('execute')->seal();

        $this->handler->runCommand();
    }

    public function testRunCommand_singleQuotedArgument_stripsQuotes(): void
    {
        $GLOBALS['argv'] = ['script.php', '--migrations', "'hello world'"];

        $this->migrationsExecutor->expects($this->once())->method('execute')->with(['hello world'], false)->seal();
        $this->resetDbExecutor->expects($this->never())->method('execute')->seal();

        $this->handler->runCommand();
    }

    public function testRunCommand_doubleQuotedArgument_stripsQuotes(): void
    {
        $GLOBALS['argv'] = ['script.php', '--migrations', '"hello world"'];

        $this->migrationsExecutor->expects($this->once())->method('execute')->with(['hello world'], false)->seal();
        $this->resetDbExecutor->expects($this->never())->method('execute')->seal();

        $this->handler->runCommand();
    }

    public function testRunCommand_unclosedSingleQuote_accumulatesTokens(): void
    {
        $GLOBALS['argv'] = ['script.php', '--migrations', "'hello", "world'"];

        $this->migrationsExecutor->expects($this->once())->method('execute')->with(['hello world'], false)->seal();
        $this->resetDbExecutor->expects($this->never())->method('execute')->seal();

        $this->handler->runCommand();
    }

    public function testRunCommand_unclosedDoubleQuote_accumulatesTokens(): void
    {
        $GLOBALS['argv'] = ['script.php', '--migrations', '"hello', 'world"'];

        $this->migrationsExecutor->expects($this->once())->method('execute')->with(['hello world'], false)->seal();
        $this->resetDbExecutor->expects($this->never())->method('execute')->seal();

        $this->handler->runCommand();
    }

    public function testRunCommand_quotedStringStopsAtNextFlag(): void
    {
        $GLOBALS['argv'] = ['script.php', '--migrations', "'hello', '--noconfirm'"];

        $this->migrationsExecutor->expects($this->once())->method('execute')->with(["hello', '--noconfirm"], false)->seal();
        $this->resetDbExecutor->expects($this->never())->method('execute')->seal();

        $this->handler->runCommand();
    }

    public function testRunCommand_missingArgv_noError(): void
    {
        unset($GLOBALS['argv']);

        $this->migrationsExecutor->expects($this->never())->method('execute')->seal();
        $this->resetDbExecutor->expects($this->never())->method('execute')->seal();

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

        $this->handler->runCommand();
    }
}
