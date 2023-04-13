<?php
declare(strict_types=1);

namespace SmolCms\Test\Unit\Service\DB;

use SmolCms\Service\DB\Attribute\Entity;
use SmolCms\Service\DB\Attribute\Id;
use SmolCms\Service\DB\EntityAttributeProcessor;
use SmolCms\TestUtils\SimpleTestCase;

class EntityAttributeProcessorTest extends SimpleTestCase
{
    private EntityAttributeProcessor $entityAttributeProcessor;

    public function testGetTableName_success()
    {
        $result = $this->entityAttributeProcessor->getEntityTableName(TestClass::class);

        self::assertSame('TableName', $result);
    }

    public function testGetEntityIdFieldName_success()
    {
        $result = $this->entityAttributeProcessor->getEntityIdFieldName(TestClass::class);

        self::assertSame('id', $result);
    }

    public function testGetEntityIdFieldName_successClassHasNoIdAttribute()
    {
        $result = $this->entityAttributeProcessor->getEntityIdFieldName(TestClassWithoutIdAttribute::class);

        self::assertNull($result);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->entityAttributeProcessor = new EntityAttributeProcessor();
    }


}

#[Entity('TableName')]
class TestClass
{
    public function __construct(
        #[Id]
        private int $id
    )
    {
    }
}

class TestClassWithoutIdAttribute
{
    public function __construct(
        private int $id
    )
    {
    }
}
