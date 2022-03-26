<?php
declare(strict_types=1);

namespace SmolCms\Test\Unit\Service\DB;

use PDO;
use PDOStatement;
use PHPUnit\Framework\MockObject\MockObject;
use SmolCms\Service\Core\CaseConverter;
use SmolCms\Service\DB\Attribute\Id;
use SmolCms\Service\DB\EntityAttributeProcessor;
use SmolCms\Service\DB\EntityService;
use SmolCms\Service\DB\QueryBuilder;
use SmolCms\TestUtils\Attributes\Mock;
use SmolCms\TestUtils\SimpleTestCase;

class EntityServiceTest extends SimpleTestCase
{
    private EntityService $entityService;

    #[Mock(PDO::class)]
    private PDO|MockObject $pdo;
    #[Mock(PDOStatement::class)]
    private PDOStatement|MockObject $PDOStatement;
    #[Mock(CaseConverter::class)]
    private CaseConverter|MockObject $caseConverter;
    #[Mock(QueryBuilder::class)]
    private QueryBuilder|MockObject $queryBuilder;
    #[Mock(EntityAttributeProcessor::class)]
    private EntityAttributeProcessor|MockObject $entityAttributeProcessor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->entityService = new class(
            $this->pdo,
            $this->caseConverter,
            $this->queryBuilder,
            $this->entityAttributeProcessor
        ) extends EntityService {};
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

    public function testSaveAsNew_success() {
        $this->caseConverter
            ->method('camelCaseToSnakeCase')
            ->willReturnMap(
                [
                    ['testFieldOne', 'test_field_one'],
                    ['testFieldNumberTwo', 'test_field_number_two'],
                    ['optional', 'optional'],
                ]
            );
        $this->pdo->method('prepare')->willReturn($this->PDOStatement);
        $this->pdo->method('lastInsertId')->willReturn('123456');
        $this->entityAttributeProcessor->method('getEntityIdFieldName')->willReturn('testFieldNumberTwo');
        $entity = new TestData('test_field_one', null, null);
        $this->entityService->saveAsNew($entity);
        self::assertSame(123456, $entity->testFieldNumberTwo);
    }
}

class TestData {
    public function __construct(
        public string $testFieldOne,
        #[Id]
        public ?int $testFieldNumberTwo,
        public ?string $optional
    )
    {
    }

    public function setTestFieldNumberTwo(?int $testFieldNumberTwo): void
    {
        $this->testFieldNumberTwo = $testFieldNumberTwo;
    }

    public function getTestFieldOne(): string
    {
        return $this->testFieldOne;
    }

    public function getTestFieldNumberTwo(): ?int
    {
        return $this->testFieldNumberTwo;
    }

    public function getOptional(): ?string
    {
        return $this->optional;
    }
}