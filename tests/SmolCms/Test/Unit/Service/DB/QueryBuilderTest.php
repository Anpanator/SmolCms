<?php
declare(strict_types=1);

namespace SmolCms\Test\Unit\Service\DB;

use PHPUnit\Framework\MockObject\MockObject;
use SmolCms\Service\Core\CaseConverter;
use SmolCms\Service\DB\Attribute\Entity;
use SmolCms\Service\DB\QueryBuilder;
use SmolCms\Service\DB\QueryCriteria;
use SmolCms\TestUtils\Attributes\Mock;
use SmolCms\TestUtils\SimpleTestCase;

class QueryBuilderTest extends SimpleTestCase
{
    private QueryBuilder $queryBuilder;

    #[Mock(CaseConverter::class)]
    private CaseConverter|MockObject $caseConverter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->queryBuilder = new QueryBuilder($this->caseConverter);

        $this->caseConverter
            ->method('camelCaseToSnakeCase')
            ->willReturnMap(
                [
                    ['id', 'id'],
                    ['testField', 'test_field'],
                ]
            );
    }

    public function testBuildQuery_successWithSelectQueryCriteria(): void
    {
        $expectedQuery = 'select id, test_field from test_table where id = :id limit 33, 200';
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
        $expectedQuery = 'select id, test_field from test_table where id < :id and id > :id limit 22, 111';
        $queryCriteria = new QueryCriteria();
        $queryCriteria
            ->select(TestEntity::class)
            ->orWhere('id < :id')
            ->andWhere('id > :id')
            ->skipResults(22)
            ->maxResults(111);

        $result = $this->queryBuilder->buildQuery($queryCriteria);
        self::assertSame($expectedQuery, strtolower($result));
    }
}

#[Entity(table: 'test_table')]
class TestEntity {

    public function __construct(
        private int $id,
        private string $testField,
    )
    {
    }
}