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

class CliCommandHandlerIntegrityTest extends SimpleTestCase
{
    #[Stub(CliCommandExecutor::class)]
    private CliCommandExecutor|MockObject $generateMigrationExecutor;

    #[Mock(CliCommandExecutor::class)]
    private CliCommandExecutor|MockObject $generateJsIntegrityExecutor;

    #[Stub(CliCommandExecutor::class)]
    private CliCommandExecutor|MockObject $migrationsExecutor;

    #[Stub(CliCommandExecutor::class)]
    private CliCommandExecutor|MockObject $resetDbExecutor;

    #[Stub(ContextService::class)]
    private ContextService|MockObject $contextService;

    private CliCommandHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();
        $GLOBALS['argv'] = ['script.php', '--generate-js-integrity'];
        $this->contextService
            ->method('isCliMode')
            ->willReturn(true);
        $this->generateMigrationExecutor
            ->method('handles')
            ->willReturn(CliCommandFlag::GENERATE_MIGRATION);
        $this->generateJsIntegrityExecutor
            ->method('handles')
            ->willReturn(CliCommandFlag::GENERATE_JS_INTEGRITY);
        $this->migrationsExecutor
            ->method('handles')
            ->willReturn(CliCommandFlag::MIGRATIONS);
        $this->resetDbExecutor
            ->method('handles')
            ->willReturn(CliCommandFlag::INIT_DB);

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

    public function testRunCommand_generateJsIntegrityFlag_callsExecutor(): void
    {
        $this->generateJsIntegrityExecutor
            ->expects($this->once())
            ->method('execute')
            ->with([], false)
            ->seal();

        $this->handler->runCommand();
    }
}
