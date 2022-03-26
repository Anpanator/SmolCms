<?php
declare(strict_types=1);

namespace SmolCms\Service\DB;


use DateTime;
use PDO;
use PDOStatement;
use ReflectionClass;
use ReflectionProperty;
use SmolCms\Service\Core\CaseConverter;

abstract class EntityService
{
    /**
     * EntityService constructor.
     */
    public function __construct(
        private   readonly PDO $pdo,
        protected readonly CaseConverter $caseConverter,
        private   readonly QueryBuilder $queryBuilder,
        protected readonly EntityAttributeProcessor $entityAttributeProcessor
    )
    {
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
     */
    public function mapResultToEntity(array $data, string $entityClass): object
    {
        $entityProps = [];
        $mappedData = [];
        foreach ($data as $field => &$value) {
            $camelCaseField = $this->caseConverter->snakeCaseToCamelCase($field);
            $mappedData[$camelCaseField] = &$value;
        }

        foreach ($this->getEntityPropertyNames($entityClass) as $propName) {
            $entityProps[$propName] = $mappedData[$propName] ?? null;
        }

        return new $entityClass(...$entityProps);
    }

    /**
     * @param object $entity
     * @return void
     *
     * Currently requires getters and setters for all entity fields.
     * TODO: Handle values set by database. Refresh if special attribute is set on any property of entity class?
     */
    public function saveAsNew(object $entity): void
    {
        $data = [];
        $propertyNames = $this->getEntityPropertyNames($entity::class);
        foreach ($propertyNames as $propName) {
            $dbField = $this->caseConverter->camelCaseToSnakeCase($propName);
            $propVal = $entity->{'get' . $propName}();
            if ($propVal instanceof DateTime) {
                $propVal = $propVal->format('Y-m-d H:i:s');
            }
            $data[$dbField] = $propVal;
        }
        $insertQuery = $this->queryBuilder->buildInsertQuery($entity::class, ...array_keys($data));
        $stmt = $this->pdo->prepare($insertQuery);
        $stmt->execute($data);
        $idField = $this->entityAttributeProcessor->getEntityIdFieldName($entity::class);
        if ($idField && $idVal = $this->pdo->lastInsertId()) {
            $entity->{'set' . $idField}((int)$idVal);
        }
    }

    // TODO: Create entity update method

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

    private function getEntityPropertyNames(string $entityClass): array
    {
        $refClass = new ReflectionClass($entityClass);
        return array_map(fn(ReflectionProperty $refProp): string => $refProp->getName(), $refClass->getProperties());
    }
}