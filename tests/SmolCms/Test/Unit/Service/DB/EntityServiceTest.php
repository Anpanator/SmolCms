<?php
declare(strict_types=1);

namespace SmolCms\Test\Unit\Service\DB;

use DateTime;
use PDO;
use PDOStatement;
use PHPUnit\Framework\MockObject\MockObject;
use SmolCms\Exception\PersistenceException;
use SmolCms\Service\Core\CaseConverter;
use SmolCms\Service\DB\EntityAttributeProcessor;
use SmolCms\Service\DB\EntityService;
use SmolCms\Service\DB\QueryBuilder;
use SmolCms\TestUtils\Attributes\Mock;
use SmolCms\TestUtils\Helper\Capture;
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

        $this->entityService = new TestEntityService(
            $this->pdo,
            $this->caseConverter,
            $this->queryBuilder,
            $this->entityAttributeProcessor
        );
    }

    public function testMapResultToEntity_success()
    {
        $testData = [
            'test_field_one' => '!!!',
            'test_field_number_two' => 100,
            'test_date_field' => '2023-01-01 00:12:13',
            'irrelevant' => 10.1
        ];

        $this->caseConverter
            ->method('snakeCaseToCamelCase')
            ->willReturnMap(
                [
                    ['test_field_one', 'testFieldOne'],
                    ['test_field_number_two', 'testFieldNumberTwo'],
                    ['test_date_field', 'testDateField'],
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

    public function testSaveAsNew_success()
    {
        $this->caseConverter
            ->method('camelCaseToSnakeCase')
            ->willReturnMap(
                [
                    ['testFieldOne', 'test_field_one'],
                    ['testFieldNumberTwo', 'test_field_number_two'],
                    ['testDateField', 'test_date_field'],
                    ['optional', 'optional'],
                ]
            );
        $this->pdo->method('prepare')->willReturn($this->PDOStatement);
        $this->pdo->method('lastInsertId')->willReturn('123456');
        $this->entityAttributeProcessor->method('getEntityIdFieldName')->willReturn('testFieldNumberTwo');
        $entity = new TestData('test_field_one', null, new DateTime(), null);
        $this->entityService->saveAsNew($entity);
        self::assertSame(123456, $entity->testFieldNumberTwo);
    }

    public function testSaveAsNew_successWithDateTimeFields()
    {
        $dateFieldName = 'dateTime';
        $dbFieldName = 'date_time';
        $expectedDateStr = '2000-01-01 12:00:00';
        $expectedDate = new DateTime($expectedDateStr);
        $capturedParams = null;

        $entity = new TestEntityWithDateField($expectedDate);
        $this->caseConverter
            ->method('camelCaseToSnakeCase')
            ->willReturnMap(
                [
                    [$dateFieldName, $dbFieldName],
                ]
            );
        $this->pdo->method('prepare')->willReturn($this->PDOStatement);
        $this->pdo->expects(self::never())->method('lastInsertId');
        $this->entityAttributeProcessor->method('getEntityIdFieldName')->willReturn(null);
        $this->PDOStatement->method('execute')->with(Capture::arg($capturedParams));

        $this->entityService->saveAsNew($entity);

        self::assertSame($expectedDateStr, $capturedParams[$dbFieldName]);
    }

    public function testUpdate_failureWithoutIdInEntity()
    {
        self::expectException(PersistenceException::class);
        $this->caseConverter
            ->method('camelCaseToSnakeCase')
            ->willReturnMap(
                [
                    ['testFieldOne', 'test_field_one'],
                    ['testFieldNumberTwo', 'test_field_number_two'],
                    ['testDateField', 'test_date_field'],
                    ['optional', 'optional'],
                ]
            );
        $testEntity = new TestData('', null, new DateTime(), null);
        $this->entityAttributeProcessor
            ->method('getEntityIdFieldName')
            ->willReturn('testFieldNumberTwo');

        $this->entityService->update($testEntity);
    }
}

readonly class TestEntityService extends EntityService
{
}

class TestEntityWithDateField
{

    public function __construct(
        private DateTime $dateTime
    )
    {
    }

    public function getDateTime(): DateTime
    {
        return $this->dateTime;
    }

    public function setDateTime(DateTime $dateTime): void
    {
        $this->dateTime = $dateTime;
    }
}

class TestData
{
    public function __construct(
        public string   $testFieldOne,
        public ?int     $testFieldNumberTwo,
        public DateTime $testDateField,
        public ?string  $optional
    )
    {
    }

    public function getTestFieldOne(): string
    {
        return $this->testFieldOne;
    }

    public function setTestFieldOne(string $testFieldOne): void
    {
        $this->testFieldOne = $testFieldOne;
    }

    public function getTestFieldNumberTwo(): ?int
    {
        return $this->testFieldNumberTwo;
    }

    public function setTestFieldNumberTwo(?int $testFieldNumberTwo): void
    {
        $this->testFieldNumberTwo = $testFieldNumberTwo;
    }

    public function getTestDateField(): DateTime
    {
        return $this->testDateField;
    }

    public function setTestDateField(DateTime $testDateField): void
    {
        $this->testDateField = $testDateField;
    }

    public function getOptional(): ?string
    {
        return $this->optional;
    }

    public function setOptional(?string $optional): void
    {
        $this->optional = $optional;
    }
}