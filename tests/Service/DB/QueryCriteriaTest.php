<?php
declare(strict_types=1);

namespace SmolCms\Test\Service\DB;

use RuntimeException;
use SmolCms\Exception\InvalidStateException;
use SmolCms\Service\DB\QueryCriteria;
use SmolCms\TestUtils\SimpleTestCase;

class QueryCriteriaTest extends SimpleTestCase
{
    public function testSelect_success(): void
    {
        $queryCriteria = new QueryCriteria();
        $result = $queryCriteria->select('TestEntity');

        self::assertSame($result, $queryCriteria);
        self::assertSame(QueryCriteria::TYPE_SELECT, $queryCriteria->getType());
        self::assertSame('TestEntity', $queryCriteria->getMainEntity());
    }

    public function testDelete_success(): void
    {
        $queryCriteria = new QueryCriteria();
        $result = $queryCriteria->delete('TestEntity');

        self::assertSame($result, $queryCriteria);
        self::assertSame(QueryCriteria::TYPE_DELETE, $queryCriteria->getType());
        self::assertSame('TestEntity', $queryCriteria->getMainEntity());
    }

    public function testUpdate_success(): void
    {
        $queryCriteria = new QueryCriteria();
        $result = $queryCriteria->update('TestEntity');

        self::assertSame($result, $queryCriteria);
        self::assertSame(QueryCriteria::TYPE_UPDATE, $queryCriteria->getType());
        self::assertSame('TestEntity', $queryCriteria->getMainEntity());
    }

    public function testSelect_typeAlreadySet_throwsException(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageIsOrContains('Query type is already set to: SELECT');
        $queryCriteria = new QueryCriteria();
        $queryCriteria
            ->select('TestEntity')
            ->select('OtherEntity');
    }

    public function testAndWhere_success(): void
    {
        $queryCriteria = new QueryCriteria();
        $result = $queryCriteria
            ->select('TestEntity')
            ->andWhere('id = :id');

        self::assertSame($result, $queryCriteria);
        self::assertSame([[QueryCriteria::KEY_AND => 'id = :id']], $queryCriteria->getWhereConditions());
    }

    public function testAndWhere_multipleConditions_success(): void
    {
        $queryCriteria = new QueryCriteria();
        $queryCriteria
            ->select('TestEntity')
            ->andWhere('id = :id')
            ->andWhere('name = :name');

        self::assertSame(
            [[QueryCriteria::KEY_AND => 'id = :id'], [QueryCriteria::KEY_AND => 'name = :name']],
            $queryCriteria->getWhereConditions()
        );
    }

    public function testOrWhere_success(): void
    {
        $queryCriteria = new QueryCriteria();
        $queryCriteria
            ->select('TestEntity')
            ->andWhere('id = :id')
            ->orWhere('name = :name');

        self::assertSame(
            [[QueryCriteria::KEY_AND => 'id = :id'], [QueryCriteria::KEY_OR => 'name = :name']],
            $queryCriteria->getWhereConditions()
        );
    }

    public function testOrWhere_firstCondition_throwsException(): void
    {
        $this->expectException(InvalidStateException::class);
        $this->expectExceptionMessageIsOrContains('Cannot use orWhere as the first where condition. Use andWhere instead.');
        $queryCriteria = new QueryCriteria();
        $queryCriteria
            ->select('TestEntity')
            ->orWhere('id = :id');
    }

    public function testWithParameters_success(): void
    {
        $queryCriteria = new QueryCriteria();
        $result = $queryCriteria->withParameters(['id' => 1]);

        self::assertSame($result, $queryCriteria);
        self::assertSame(['id' => 1], $queryCriteria->getParameters());
    }

    public function testWithParameters_overwritesPrevious_success(): void
    {
        $queryCriteria = new QueryCriteria();
        $queryCriteria->withParameters(['id' => 1]);
        $queryCriteria->withParameters(['name' => 'test']);

        self::assertSame(['name' => 'test'], $queryCriteria->getParameters());
    }

    public function testMaxResults_success(): void
    {
        $queryCriteria = new QueryCriteria();
        $result = $queryCriteria->maxResults(10);

        self::assertSame($result, $queryCriteria);
        self::assertSame(10, $queryCriteria->getLimit());
    }

    public function testSkipResults_success(): void
    {
        $queryCriteria = new QueryCriteria();
        $result = $queryCriteria->skipResults(5);

        self::assertSame($result, $queryCriteria);
        self::assertSame(5, $queryCriteria->getOffset());
    }

    public function testDefaults_areNull(): void
    {
        $queryCriteria = new QueryCriteria();
        $queryCriteria->select('TestEntity');

        self::assertNull($queryCriteria->getOffset());
        self::assertNull($queryCriteria->getLimit());
        self::assertSame([], $queryCriteria->getWhereConditions());
        self::assertSame([], $queryCriteria->getParameters());
    }
}
