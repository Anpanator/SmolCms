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

    #[Stub(CliCommandExecutor::class)]
    private CliCommandExecutor|MockObject $generateJsIntegrityExecutor;

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
        $this->contextService
            ->method('isCliMode')
            ->willReturn(true);
        $this->generateMigrationExecutor
            ->method('handles')
            ->willReturn(CliCommandFlag::GENERATE_MIGRATION);
        $this->generateMigrationExecutor
            ->method('helptext')
            ->willReturn('Generate a new migration file.');
        $this->generateJsIntegrityExecutor
            ->method('handles')
            ->willReturn(CliCommandFlag::GENERATE_JS_INTEGRITY);
        $this->generateJsIntegrityExecutor
            ->method('helptext')
            ->willReturn('Generate SHA-384 integrity attributes for local JavaScript in the HTML shell.');
        $this->migrationsExecutor
            ->method('handles')
            ->willReturn(CliCommandFlag::MIGRATIONS);
        $this->migrationsExecutor
            ->method('helptext')
            ->willReturn('Execute pending database migrations.');
        $this->resetDbExecutor
            ->method('handles')
            ->willReturn(CliCommandFlag::INIT_DB);
        $this->resetDbExecutor
            ->method('helptext')
            ->willReturn('Initialize an empty database; pass --reset to drop and recreate existing tables.');
        $this->handler = new CliCommandHandler(
            $this->contextService,
            $this->generateMigrationExecutor,
            $this->generateJsIntegrityExecutor,
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
        $this->migrationsExecutor
            ->expects($this->never())
            ->method('execute')
            ->seal();
        $this->resetDbExecutor
            ->expects($this->never())
            ->method('execute')
            ->seal();
        $this->generateMigrationExecutor
            ->expects($this->never())
            ->method('execute')
            ->seal();

        $this->expectOutputString(
            "Available commands:\n"
            . "  --generate-migration  Generate a new migration file.\n"
            . "  --generate-js-integrity  Generate SHA-384 integrity attributes for local JavaScript in the HTML shell.\n"
            . "  --migrations  Execute pending database migrations.\n"
            . "  --init-db  Initialize an empty database; pass --reset to drop and recreate existing tables.\n"
            . "  --noconfirm  Skip confirmation prompts.\n"
        );
        $this->handler->runCommand();
    }

    public function testRunCommand_migrationsFlag_callsExecutor(): void
    {
        $GLOBALS['argv'] = ['script.php', '--migrations'];

        $this->migrationsExecutor
            ->expects($this->once())
            ->method('execute')
            ->with([], false)
            ->seal();
        $this->resetDbExecutor
            ->expects($this->never())
            ->method('execute')
            ->seal();
        $this->generateMigrationExecutor
            ->expects($this->never())
            ->method('execute')
            ->seal();

        $this->handler->runCommand();
    }

    public function testRunCommand_noconfirmFlag_outputsDefaultMessage(): void
    {
        $GLOBALS['argv'] = ['script.php', '--noconfirm'];

        $this->migrationsExecutor
            ->expects($this->never())
            ->method('execute')
            ->seal();
        $this->resetDbExecutor
            ->expects($this->never())
            ->method('execute')
            ->seal();
        $this->generateMigrationExecutor
            ->expects($this->never())
            ->method('execute')
            ->seal();

        $this->expectOutputString(
            "Available commands:\n"
            . "  --generate-migration  Generate a new migration file.\n"
            . "  --generate-js-integrity  Generate SHA-384 integrity attributes for local JavaScript in the HTML shell.\n"
            . "  --migrations  Execute pending database migrations.\n"
            . "  --init-db  Initialize an empty database; pass --reset to drop and recreate existing tables.\n"
            . "  --noconfirm  Skip confirmation prompts.\n"
        );
        $this->handler->runCommand();
    }

    public function testRunCommand_noconfirmWithMigrations_passesTrue(): void
    {
        $GLOBALS['argv'] = ['script.php', '--migrations', '--noconfirm'];

        $this->migrationsExecutor
            ->expects($this->once())
            ->method('execute')
            ->with([], true)
            ->seal();
        $this->resetDbExecutor
            ->expects($this->never())
            ->method('execute')
            ->seal();
        $this->generateMigrationExecutor
            ->expects($this->never())
            ->method('execute')
            ->seal();

        $this->handler->runCommand();
    }

    public function testRunCommand_multipleFlags_callsMultipleExecutors(): void
    {
        $GLOBALS['argv'] = ['script.php', '--migrations', '--init-db'];

        $this->migrationsExecutor
            ->expects($this->once())
            ->method('execute')
            ->seal();
        $this->resetDbExecutor
            ->expects($this->once())
            ->method('execute')
            ->seal();
        $this->generateMigrationExecutor
            ->expects($this->never())
            ->method('execute')
            ->seal();

        $this->handler->runCommand();
    }

    public function testRunCommand_flagWithArguments_passesArguments(): void
    {
        $GLOBALS['argv'] = ['script.php', '--migrations', 'arg1', 'arg2'];

        $this->migrationsExecutor
            ->expects($this->once())
            ->method('execute')
            ->with(['arg1', 'arg2'], false)
            ->seal();
        $this->resetDbExecutor
            ->expects($this->never())
            ->method('execute')
            ->seal();
        $this->generateMigrationExecutor
            ->expects($this->never())
            ->method('execute')
            ->seal();

        $this->handler->runCommand();
    }

    public function testRunCommand_missingArgv_noError(): void
    {
        unset($GLOBALS['argv']);

        $this->migrationsExecutor
            ->expects($this->never())
            ->method('execute')
            ->seal();
        $this->resetDbExecutor
            ->expects($this->never())
            ->method('execute')
            ->seal();
        $this->generateMigrationExecutor
            ->expects($this->never())
            ->method('execute')
            ->seal();

        $this->expectOutputString(
            "Available commands:\n"
            . "  --generate-migration  Generate a new migration file.\n"
            . "  --generate-js-integrity  Generate SHA-384 integrity attributes for local JavaScript in the HTML shell.\n"
            . "  --migrations  Execute pending database migrations.\n"
            . "  --init-db  Initialize an empty database; pass --reset to drop and recreate existing tables.\n"
            . "  --noconfirm  Skip confirmation prompts.\n"
        );
        $this->handler->runCommand();
    }

    public function testRunCommand_nonFlagTokensBeforeFirstFlag_ignored(): void
    {
        $GLOBALS['argv'] = ['script.php', 'some', 'junk', '--migrations'];

        $this->migrationsExecutor
            ->expects($this->once())
            ->method('execute')
            ->with([], false)
            ->seal();
        $this->resetDbExecutor
            ->expects($this->never())
            ->method('execute')
            ->seal();
        $this->generateMigrationExecutor
            ->expects($this->never())
            ->method('execute')
            ->seal();

        $this->handler->runCommand();
    }

    public function testRunCommand_notCliMode_returnsFalse(): void
    {
        $this->contextService = $this->createStub(ContextService::class);
        $this->contextService
            ->method('isCliMode')
            ->willReturn(false);

        $this->handler = new CliCommandHandler(
            $this->contextService,
            $this->generateMigrationExecutor,
            $this->generateJsIntegrityExecutor,
            $this->migrationsExecutor,
            $this->resetDbExecutor,
        );

        $this->migrationsExecutor
            ->expects($this->never())
            ->method('execute')
            ->seal();
        $this->resetDbExecutor
            ->expects($this->never())
            ->method('execute')
            ->seal();
        $this->generateMigrationExecutor
            ->expects($this->never())
            ->method('execute')
            ->seal();


        self::assertFalse($this->handler->runCommand());
    }

    public function testRunCommand_initDbFlag_callsExecutor(): void
    {
        $GLOBALS['argv'] = ['script.php', '--init-db'];

        $this->migrationsExecutor
            ->expects($this->never())
            ->method('execute')
            ->seal();
        $this->resetDbExecutor
            ->expects($this->once())
            ->method('execute')
            ->with([], false)
            ->seal();
        $this->generateMigrationExecutor
            ->expects($this->never())
            ->method('execute')
            ->seal();

        $this->handler->runCommand();
    }

    public function testRunCommand_resetModifierWithoutInitDb_doesNotRunExecutor(): void
    {
        $GLOBALS['argv'] = ['script.php', '--reset'];

        $this->migrationsExecutor
            ->expects($this->never())
            ->method('execute')
            ->seal();
        $this->resetDbExecutor
            ->expects($this->never())
            ->method('execute')
            ->seal();
        $this->generateMigrationExecutor
            ->expects($this->never())
            ->method('execute')
            ->seal();

        $this->expectOutputString(
            "Available commands:\n"
            . "  --generate-migration  Generate a new migration file.\n"
            . "  --generate-js-integrity  Generate SHA-384 integrity attributes for local JavaScript in the HTML shell.\n"
            . "  --migrations  Execute pending database migrations.\n"
            . "  --init-db  Initialize an empty database; pass --reset to drop and recreate existing tables.\n"
            . "  --noconfirm  Skip confirmation prompts.\n"
        );
        $this->handler->runCommand();
    }

    public function testRunCommand_generateMigrationFlag_callsExecutor(): void
    {
        $GLOBALS['argv'] = ['script.php', '--generate-migration'];

        $this->migrationsExecutor
            ->expects($this->never())
            ->method('execute')
            ->seal();
        $this->resetDbExecutor
            ->expects($this->never())
            ->method('execute')
            ->seal();
        $this->generateMigrationExecutor
            ->expects($this->once())
            ->method('execute')
            ->seal();

        $this->expectOutputString('');
        $this->handler->runCommand();
    }

    public function testRunCommand_noconfirmWithInitDb_passesTrue(): void
    {
        $GLOBALS['argv'] = ['script.php', '--init-db', '--noconfirm'];

        $this->migrationsExecutor
            ->expects($this->never())
            ->method('execute')
            ->seal();
        $this->resetDbExecutor
            ->expects($this->once())
            ->method('execute')
            ->with([], true)
            ->seal();
        $this->generateMigrationExecutor
            ->expects($this->never())
            ->method('execute')
            ->seal();

        $this->handler->runCommand();
    }

    public function testRunCommand_initDbWithResetModifier_passesResetArgument(): void
    {
        $GLOBALS['argv'] = ['script.php', '--init-db', '--reset'];

        $this->migrationsExecutor
            ->expects($this->never())
            ->method('execute')
            ->seal();
        $this->resetDbExecutor
            ->expects($this->once())
            ->method('execute')
            ->with(['--reset'], false)
            ->seal();
        $this->generateMigrationExecutor
            ->expects($this->never())
            ->method('execute')
            ->seal();

        $this->handler->runCommand();
    }

    public function testRunCommand_resetModifierAndNoconfirm_passBothToInitDb(): void
    {
        $GLOBALS['argv'] = ['script.php', '--init-db', '--reset', '--noconfirm'];

        $this->migrationsExecutor
            ->expects($this->never())
            ->method('execute')
            ->seal();
        $this->resetDbExecutor
            ->expects($this->once())
            ->method('execute')
            ->with(['--reset'], true)
            ->seal();
        $this->generateMigrationExecutor
            ->expects($this->never())
            ->method('execute')
            ->seal();

        $this->handler->runCommand();
    }
}
