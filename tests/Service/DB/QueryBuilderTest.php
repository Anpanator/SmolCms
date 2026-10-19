<?php
declare(strict_types=1);

namespace SmolCms\Test\Service\DB;

use PHPUnit\Framework\MockObject\MockObject;
use SmolCms\Exception\InvalidStateException;
use SmolCms\Service\Core\CaseConverter;
use SmolCms\Service\DB\EntityAttributeProcessor;
use SmolCms\Service\DB\QueryBuilder;
use SmolCms\Service\DB\QueryCriteria;
use SmolCms\TestUtils\Attributes\Mock;
use SmolCms\TestUtils\SimpleTestCase;

class QueryBuilderTest extends SimpleTestCase
{
    private QueryBuilder $queryBuilder;

    #[Mock(CaseConverter::class)]
    private CaseConverter|MockObject $caseConverter;
    #[Mock(EntityAttributeProcessor::class)]
    private EntityAttributeProcessor|MockObject $entityAttributeProcessor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->queryBuilder = new QueryBuilder($this->caseConverter, $this->entityAttributeProcessor);
        $this->entityAttributeProcessor
            ->method('getEntityTableName')
            ->willReturn('test_table');
        $this->entityAttributeProcessor
            ->method('getEntityTableName')
            ->willReturn('test_table')
            ->seal();
        $this->caseConverter
            ->method('camelCaseToSnakeCase')
            ->willReturnMap(
                [
                    ['id', 'id'],
                    ['testField', 'test_field'],
                ]
            )
            ->seal();
    }

    public function testBuildQuery_successWithSelectQueryCriteria(): void
    {
        $expectedQuery = 'select id, test_field from test_table where 1 and id = :id limit 33, 200';
        $queryCriteria = new QueryCriteria();
        $queryCriteria
            ->select(TestEntity::class)
            ->andWhere('id = :id')
            ->skipResults(33)
            ->maxResults(200);

        $result = $this->queryBuilder->buildQuery($queryCriteria);
        self::assertSame($expectedQuery, strtolower($result));
    }

    public function testBuildQuery_successWithSelectQueryCriteriaMultipleConditions(): void
    {
        $expectedQuery = 'select id, test_field from test_table where 1 and id > :id or id < :id limit 22, 111';
        $queryCriteria = new QueryCriteria();
        $queryCriteria
            ->select(TestEntity::class)
            ->andWhere('id > :id')
            ->orWhere('id < :id')
            ->skipResults(22)
            ->maxResults(111);

        $result = $this->queryBuilder->buildQuery($queryCriteria);
        self::assertSame($expectedQuery, strtolower($result));
    }

    public function testBuildInsertQuery_success()
    {
        $expectedQuery = 'insert into test_table (id, test_field) values (:id, :test_field)';
        $resultQuery = $this->queryBuilder->buildInsertQuery(TestEntity::class);
        self::assertSame($expectedQuery, strtolower($resultQuery));
    }

    public function testBuildDeleteQuery_successWithWhere(): void
    {
        $expectedQuery = 'delete from test_table where 1 and id = :id';
        $queryCriteria = new QueryCriteria();
        $queryCriteria
            ->delete(TestEntity::class)
            ->andWhere('id = :id');

        $resultQuery = $this->queryBuilder->buildQuery($queryCriteria);

        self::assertSame($expectedQuery, strtolower($resultQuery));
    }

    public function testBuildSelectQuery_successWithoutWhereClause(): void
    {
        $expectedQuery = 'select id, test_field from test_table';
        $queryCriteria = new QueryCriteria();
        $queryCriteria->select(TestEntity::class);

        $resultQuery = $this->queryBuilder->buildQuery($queryCriteria);

        self::assertSame($expectedQuery, strtolower($resultQuery));
    }

    public function testBuildSelectQuery_successWithOrderBy(): void
    {
        $expectedQuery = 'select id, test_field from test_table where 1 and id = :id order by test_field desc';
        $queryCriteria = new QueryCriteria();
        $queryCriteria
            ->select(TestEntity::class)
            ->andWhere('id = :id')
            ->orderBy('test_field', QueryCriteria::ORDER_DESC);

        $resultQuery = $this->queryBuilder->buildQuery($queryCriteria);

        self::assertSame($expectedQuery, strtolower($resultQuery));
    }

    public function testBuildSelectQuery_successWithOrderByAndLimit(): void
    {
        $expectedQuery = 'select id, test_field from test_table order by test_field asc limit 10';
        $queryCriteria = new QueryCriteria();
        $queryCriteria
            ->select(TestEntity::class)
            ->orderBy('test_field')
            ->maxResults(10);

        $resultQuery = $this->queryBuilder->buildQuery($queryCriteria);

        self::assertSame($expectedQuery, strtolower($resultQuery));
    }

    public function testBuildSelectQuery_ignoresOrderByForNonSelectQueries(): void
    {
        $expectedQuery = 'delete from test_table where 1 and id = :id';
        $queryCriteria = new QueryCriteria();
        $queryCriteria
            ->delete(TestEntity::class)
            ->andWhere('id = :id')
            ->orderBy('test_field');

        $resultQuery = $this->queryBuilder->buildQuery($queryCriteria);

        self::assertSame($expectedQuery, strtolower($resultQuery));
    }

    public function testBuildSelectQuery_preservesZeroLimit(): void
    {
        $expectedQuery = 'select id, test_field from test_table limit 0';
        $queryCriteria = new QueryCriteria();
        $queryCriteria
            ->select(TestEntity::class)
            ->maxResults(0);

        $resultQuery = $this->queryBuilder->buildQuery($queryCriteria);

        self::assertSame($expectedQuery, strtolower($resultQuery));
    }

    public function testBuildUpdateQuery_success(): void
    {
        $expectedQuery = 'update test_table set id = :id,test_field = :test_field';
        $queryCriteria = new QueryCriteria();
        $queryCriteria
            ->update(TestEntity::class);
        $resultQuery = $this->queryBuilder->buildQuery($queryCriteria);
        self::assertSame($expectedQuery, strtolower($resultQuery));
    }

    public function testBuildUpdateQuery_successWithWhere(): void
    {
        $expectedQuery = 'update test_table set id = :id,test_field = :test_field where 1 and id = :id';
        $queryCriteria = new QueryCriteria();
        $queryCriteria
            ->update(TestEntity::class)
            ->andWhere('id = :id');
        $resultQuery = $this->queryBuilder->buildQuery($queryCriteria);
        self::assertSame($expectedQuery, strtolower($resultQuery));
    }

    public function testBuildCountQuery_successWithoutWhere(): void
    {
        $expectedQuery = 'select count(*) from test_table';
        $queryCriteria = new QueryCriteria();
        $queryCriteria->select(TestEntity::class);

        $resultQuery = $this->queryBuilder->buildCountQuery($queryCriteria);

        self::assertSame($expectedQuery, strtolower($resultQuery));
    }

    public function testBuildCountQuery_successWithWhere(): void
    {
        $expectedQuery = 'select count(*) from test_table where 1 and id = :id or id < :id';
        $queryCriteria = new QueryCriteria();
        $queryCriteria
            ->select(TestEntity::class)
            ->andWhere('id = :id')
            ->orWhere('id < :id');

        $resultQuery = $this->queryBuilder->buildCountQuery($queryCriteria);

        self::assertSame($expectedQuery, strtolower($resultQuery));
    }

    public function testBuildCountQuery_ignoresLimitOffsetAndOrdering(): void
    {
        $expectedQuery = 'select count(*) from test_table where 1 and id = :id';
        $queryCriteria = new QueryCriteria();
        $queryCriteria
            ->select(TestEntity::class)
            ->andWhere('id = :id')
            ->orderBy('test_field')
            ->skipResults(40)
            ->maxResults(10);

        $resultQuery = $this->queryBuilder->buildCountQuery($queryCriteria);

        self::assertSame($expectedQuery, strtolower($resultQuery));
    }

    public function testBuildCountQuery_rejectsNonSelectCriteria(): void
    {
        $queryCriteria = new QueryCriteria();
        $queryCriteria
            ->delete(TestEntity::class)
            ->andWhere('id = :id');

        $this->expectException(InvalidStateException::class);
        $this->queryBuilder->buildCountQuery($queryCriteria);
    }


}

class TestEntity {

    public function __construct(
        private int $id,
        private string $testField,
    )
    {
    }
}
