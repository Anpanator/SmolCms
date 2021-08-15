<?php
declare(strict_types=1);

namespace SmolCms\Service\DB;


use PDO;
use PDOStatement;
use ReflectionClass;
use ReflectionException;
use SmolCms\Service\Core\CaseConverter;

abstract class EntityService
{
    /**
     * EntityService constructor.
     */
    public function __construct(
        private PDO             $pdo,
        protected CaseConverter $caseConverter,
        private QueryBuilder $queryBuilder,
    )
    {
    }

    /**
     * Use this if you want to run a particular query multiple times with different values.
     * You'll get a PDOStatement where you can update the parameters manually or simply re-execute it if needed.
     *
     * @param QueryCriteria $qc
     * @return PDOStatement
     */
    protected function prepare(QueryCriteria $qc): PDOStatement
    {
        $query = $this->queryBuilder->buildQuery($qc);
        return $this->pdo->prepare($query);
    }

    /**
     * @param QueryCriteria $qc
     * @return array<object>
     */
    protected function execute(QueryCriteria $qc): array
    {
        $stmt = $this->prepare($qc);
        return $this->fetchResults($stmt, $qc->getParameters(), $qc->getMainEntity());
    }

    protected function fetchResults(PDOStatement $stmt, array $parameters, string $mappingClass): array
    {
        $hasResults = $stmt->execute($parameters);
        $result = [];
        if (!$hasResults) {
            return $result;
        }
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $result[] = $this->mapResultToEntity($row, $mappingClass);
        }

        return $result;
    }

    /**
     * Maps a result from the DB to an Entity object. Unknown fields are dropped and
     * fields in the entity that don't exist in $data are initialized with null.
     *
     * Note that the entity will be created through its constructor, so all fields
     * must exist in the constructor.
     *
     * DB fields are expected to be snake case and constructor parameters are expected
     * to be camel case.
     *
     * TODO: Cache reflection result
     *
     * @param array<string, mixed> $data
     * @param string $entityClass
     * @return object
     * @throws ReflectionException
     */
    public function mapResultToEntity(array $data, string $entityClass): object
    {
        $entityProps = [];
        $mappedData = [];
        foreach ($data as $field => &$value) {
            $camelCaseField = $this->caseConverter->snakeCaseToCamelCase($field);
            $mappedData[$camelCaseField] = &$value;
        }

        $refClass = new ReflectionClass($entityClass);
        $refProps = $refClass->getProperties();
        foreach ($refProps as $refProp) {
            $propName = $refProp->getName();
            $entityProps[$propName] = $mappedData[$propName] ?? null;
        }

        return new $entityClass(...$entityProps);
    }
}