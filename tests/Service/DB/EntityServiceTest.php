<?php
declare(strict_types=1);

namespace SmolCms\Test\Service\DB;

use DateTime;
use PDO;
use PDOStatement;
use PHPUnit\Framework\MockObject\MockObject;
use RuntimeException;
use SmolCms\Data\Constant\AccessLevel;
use SmolCms\Exception\PersistenceException;
use SmolCms\Service\Core\CaseConverter;
use SmolCms\Service\DB\EntityAttributeProcessor;
use SmolCms\Service\DB\EntityService;
use SmolCms\Service\DB\QueryBuilder;
use SmolCms\TestUtils\Attributes\Mock;
use SmolCms\TestUtils\Attributes\Stub;
use SmolCms\TestUtils\Helper\Capture;
use SmolCms\TestUtils\SimpleTestCase;

class EntityServiceTest extends SimpleTestCase
{
    private EntityService $entityService;

    #[Mock(PDO::class)]
    private PDO|MockObject $pdo;
    #[Mock(PDOStatement::class)]
    private PDOStatement|MockObject $PDOStatement;
    #[Stub(CaseConverter::class)]
    private CaseConverter|MockObject $caseConverter;
    #[Stub(QueryBuilder::class)]
    private QueryBuilder|MockObject $queryBuilder;
    #[Stub(EntityAttributeProcessor::class)]
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
        $this->pdo
            ->expects($this->never())
            ->method('prepare')
            ->seal();
        $this->PDOStatement
            ->expects($this->never())
            ->method('execute')
            ->seal();

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
            )
            ->seal();

        /** @var TestData $result */
        $result = $this->entityService->mapResultToEntity($testData, TestData::class);
        self::assertInstanceOf(TestData::class, $result);
        self::assertSame('!!!', $result->testFieldOne);
        self::assertSame(100, $result->testFieldNumberTwo);
        self::assertSame('2023-01-01 00:12:13', $result->testDateField->format('Y-m-d H:i:s'));
        self::assertNull($result->optional);
    }

    public function testMapResultToEntity_mapsEnumAndDateTime(): void
    {
        $this->pdo
            ->expects($this->never())
            ->method('prepare')
            ->seal();
        $this->PDOStatement
            ->expects($this->never())
            ->method('execute')
            ->seal();

        $testData = [
            'created' => '2024-02-03 04:05:06',
            'access_level' => (string)AccessLevel::MASTER->value,
        ];

        $this->caseConverter
            ->method('snakeCaseToCamelCase')
            ->willReturnMap(
                [
                    ['created', 'created'],
                    ['access_level', 'accessLevel'],
                ]
            )
            ->seal();

        /** @var TestDataWithEnumAndDateTime $result */
        $result = $this->entityService->mapResultToEntity($testData, TestDataWithEnumAndDateTime::class);

        self::assertSame('2024-02-03 04:05:06', $result->getCreated()->format('Y-m-d H:i:s'));
        self::assertSame(AccessLevel::MASTER, $result->getAccessLevel());
    }

    public function testSaveAsNew_success(): void
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
            )
            ->seal();
        $this->pdo
            ->method('prepare')
            ->willReturn($this->PDOStatement);
        $this->pdo
            ->method('lastInsertId')
            ->willReturn('123456')
            ->seal();
        $this->entityAttributeProcessor
            ->method('getEntityIdFieldName')
            ->willReturn('testFieldNumberTwo')
            ->seal();
        $this->PDOStatement
            ->expects($this->once())
            ->method('execute')
            ->seal();
        $entity = new TestData('test_field_one', null, new DateTime(), null);
        $this->entityService->saveAsNew($entity);
        self::assertSame(123456, $entity->testFieldNumberTwo);
    }

    public function testSaveAsNew_successWithDateTimeFields(): void
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
            )
            ->seal();
        $this->pdo
            ->method('prepare')
            ->willReturn($this->PDOStatement);
        $this->pdo
            ->expects(self::never())
            ->method('lastInsertId')
            ->seal();
        $this->entityAttributeProcessor
            ->method('getEntityIdFieldName')
            ->willReturn(null)
            ->seal();
        $this->PDOStatement
            ->expects($this->once())
            ->method('execute')
            ->with(Capture::arg($capturedParams))
            ->seal();

        $this->entityService->saveAsNew($entity);

        self::assertSame($expectedDateStr, $capturedParams[$dbFieldName]);
    }

    public function testUpdate_success(): void
    {
        $expectedDate = new DateTime('2000-01-01 12:00:00');
        $entity = new TestData('updated', 123, $expectedDate, 'optional');

        $this->caseConverter
            ->method('camelCaseToSnakeCase')
            ->willReturnMap(
                [
                    ['testFieldOne', 'test_field_one'],
                    ['testFieldNumberTwo', 'test_field_number_two'],
                    ['testDateField', 'test_date_field'],
                    ['optional', 'optional'],
                ]
            )
            ->seal();
        $this->entityAttributeProcessor
            ->method('getEntityIdFieldName')
            ->willReturn('testFieldNumberTwo')
            ->seal();
        $this->queryBuilder
            ->method('buildQuery')
            ->willReturn('UPDATE test_table SET test_field_one = :test_field_one')
            ->seal();
        $this->pdo
            ->method('prepare')
            ->willReturn($this->PDOStatement)
            ->seal();
        $this->PDOStatement
            ->expects($this->once())
            ->method('execute')
            ->with(
                [
                    'test_field_one' => 'updated',
                    'test_field_number_two' => 123,
                    'test_date_field' => '2000-01-01 12:00:00',
                    'optional' => 'optional',
                ]
            )
            ->seal();

        $this->entityService->update($entity);
    }

    public function testSaveAsNew_databaseFailure_isWrapped(): void
    {
        $databaseException = new RuntimeException('database unavailable');
        $entity = new TestEntityWithDateField(new DateTime('2000-01-01 12:00:00'));

        $this->caseConverter
            ->method('camelCaseToSnakeCase')
            ->willReturnMap(
                [
                    ['dateTime', 'date_time'],
                ]
            )
            ->seal();
        $this->queryBuilder
            ->method('buildInsertQuery')
            ->willReturn('INSERT INTO test_table (date_time) VALUES (:date_time)')
            ->seal();
        $this->pdo
            ->method('prepare')
            ->willReturn($this->PDOStatement)
            ->seal();
        $this->PDOStatement
            ->expects($this->once())
            ->method('execute')
            ->willThrowException($databaseException)
            ->seal();

        try {
            $this->entityService->saveAsNew($entity);
            self::fail('Expected saveAsNew() to throw a PersistenceException.');
        } catch (PersistenceException $exception) {
            self::assertSame('Failed to save new entity: ' . $entity::class, $exception->getMessage());
            self::assertSame($databaseException, $exception->getPrevious());
        }
    }

    public function testUpdate_databaseFailure_isWrapped(): void
    {
        $databaseException = new RuntimeException('database unavailable');
        $entity = new TestData('updated', 123, new DateTime('2000-01-01 12:00:00'), null);

        $this->caseConverter
            ->method('camelCaseToSnakeCase')
            ->willReturnMap(
                [
                    ['testFieldOne', 'test_field_one'],
                    ['testFieldNumberTwo', 'test_field_number_two'],
                    ['testDateField', 'test_date_field'],
                    ['optional', 'optional'],
                ]
            )
            ->seal();
        $this->entityAttributeProcessor
            ->method('getEntityIdFieldName')
            ->willReturn('testFieldNumberTwo')
            ->seal();
        $this->queryBuilder
            ->method('buildQuery')
            ->willReturn('UPDATE test_table SET test_field_one = :test_field_one')
            ->seal();
        $this->pdo
            ->method('prepare')
            ->willReturn($this->PDOStatement)
            ->seal();
        $this->PDOStatement
            ->expects($this->once())
            ->method('execute')
            ->willThrowException($databaseException)
            ->seal();

        try {
            $this->entityService->update($entity);
            self::fail('Expected update() to throw a PersistenceException.');
        } catch (PersistenceException $exception) {
            self::assertSame('Failed to update entity: ' . $entity::class, $exception->getMessage());
            self::assertSame($databaseException, $exception->getPrevious());
        }
    }

    public function testUpdate_failureWithoutIdInEntity(): void
    {
        $this->pdo
            ->expects($this->never())
            ->method('prepare')
            ->seal();
        $this->PDOStatement
            ->expects($this->never())
            ->method('execute')
            ->seal();

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
            )
            ->seal();
        $testEntity = new TestData('', null, new DateTime(), null);
        $this->entityAttributeProcessor
            ->method('getEntityIdFieldName')
            ->willReturn('testFieldNumberTwo')
            ->seal();

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

class TestDataWithEnumAndDateTime
{
    public function __construct(
        private DateTime    $created,
        private AccessLevel $accessLevel,
    )
    {
    }

    public function getCreated(): DateTime
    {
        return $this->created;
    }

    public function getAccessLevel(): AccessLevel
    {
        return $this->accessLevel;
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
