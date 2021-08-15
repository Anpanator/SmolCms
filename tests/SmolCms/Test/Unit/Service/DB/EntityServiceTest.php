<?php
declare(strict_types=1);

namespace SmolCms\Test\Unit\Service\DB;

use PDO;
use PHPUnit\Framework\MockObject\MockObject;
use SmolCms\Service\Core\CaseConverter;
use SmolCms\Service\DB\EntityService;
use SmolCms\Service\DB\QueryBuilder;
use SmolCms\TestUtils\Attributes\Mock;
use SmolCms\TestUtils\SimpleTestCase;

class EntityServiceTest extends SimpleTestCase
{
    private EntityService $entityService;

    #[Mock(PDO::class)]
    private PDO|MockObject $pdo;
    #[Mock(CaseConverter::class)]
    private CaseConverter|MockObject $caseConverter;
    #[Mock(QueryBuilder::class)]
    private QueryBuilder|MockObject $queryBuilder;

    protected function setUp(): void
    {
        parent::setUp();

        $this->entityService = new class($this->pdo, $this->caseConverter, $this->queryBuilder) extends EntityService {};
    }

    public function testMapResultToEntity_success() {
        $testData = ['test_field_one' => '!!!', 'test_field_number_two' => 100, 'irrelevant' => 10.1];

        $this->caseConverter
            ->method('snakeCaseToCamelCase')
            ->willReturnMap(
                [
                    ['test_field_one', 'testFieldOne'],
                    ['test_field_number_two', 'testFieldNumberTwo'],
                    ['irrelevant', 'irrelevant'],
                ]
            );

        /** @var TestData $result */
        $result = $this->entityService->mapResultToEntity($testData, TestData::class);
        self::assertInstanceOf(TestData::class, $result);
        self::assertSame('!!!', $result->testFieldOne);
        self::assertSame(100, $result->testFieldNumberTwo);
        self::assertNull($result->optional);
    }
}

class TestData {
    public function __construct(
        public string $testFieldOne,
        public int $testFieldNumberTwo,
        public ?string $optional
    )
    {
    }
}