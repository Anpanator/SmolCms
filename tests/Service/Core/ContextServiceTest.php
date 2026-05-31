<?php
declare(strict_types=1);

namespace SmolCms\Test\Service\Core;

use InvalidArgumentException;
use SmolCms\Data\Constant\ContextKey;
use SmolCms\Service\Core\ContextService;
use SmolCms\TestUtils\SimpleTestCase;

class ContextServiceTest extends SimpleTestCase
{
    private ContextService $contextService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->contextService = new ContextService();
    }

    public function testGetContext_ReturnsNullForUnsetKey(): void
    {
        self::assertNull($this->contextService->getContext(ContextKey::PAGE_TITLE));
    }

    public function testSetContext_StoresAndRetrievesValue(): void
    {
        $this->contextService->setContext(ContextKey::PAGE_TITLE, 'My Page');
        self::assertSame('My Page', $this->contextService->getContext(ContextKey::PAGE_TITLE));
    }

    public function testSetContext_OverwritesPreviousValue(): void
    {
        $this->contextService->setContext(ContextKey::PAGE_TITLE, 'First');
        $this->contextService->setContext(ContextKey::PAGE_TITLE, 'Second');
        self::assertSame('Second', $this->contextService->getContext(ContextKey::PAGE_TITLE));
    }

    public function testSetContext_ThrowsOnTypeMismatch(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Type mismatch/');
        $this->contextService->setContext(ContextKey::PAGE_TITLE, 42);
    }

    public function testSetContext_StoresNullValue(): void
    {
        $this->contextService->setContext(ContextKey::PAGE_TITLE, null);
        self::assertNull($this->contextService->getContext(ContextKey::PAGE_TITLE));
    }
}
