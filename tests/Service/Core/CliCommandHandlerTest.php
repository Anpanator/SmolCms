<?php
declare(strict_types=1);

namespace SmolCms\Test\Service\Core;

use PHPUnit\Framework\MockObject\MockObject;
use SmolCms\Data\Constant\CliCommandFlag;
use SmolCms\Service\Core\CliCommand\CliCommandExecutor;
use SmolCms\Service\Core\CliCommandHandler;
use SmolCms\Service\Core\ContextService;
use SmolCms\TestUtils\Attributes\Mock;
use SmolCms\TestUtils\SimpleTestCase;

class CliCommandHandlerTest extends SimpleTestCase
{
    #[Mock(CliCommandExecutor::class)]
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
        $this->migrationsExecutor->method('handles')->willReturn(CliCommandFlag::MIGRATIONS);
        $this->resetDbExecutor->method('handles')->willReturn(CliCommandFlag::RESET_DB);
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

        $this->expectOutputString("No command specified. Available flags: --generate-migration, --migrations, --noconfirm, --reset-db\n");
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

        $this->expectOutputString("No command specified. Available flags: --generate-migration, --migrations, --noconfirm, --reset-db\n");
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

        $this->expectOutputString("No command specified. Available flags: --generate-migration, --migrations, --noconfirm, --reset-db\n");
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
