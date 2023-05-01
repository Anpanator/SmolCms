<?php
declare(strict_types=1);

namespace SmolCms\Service\DB;


use DateTime;
use Exception;
use PDO;
use PDOStatement;
use ReflectionClass;
use ReflectionNamedType;
use SmolCms\Exception\PersistenceException;
use SmolCms\Service\Core\CaseConverter;
use Throwable;

abstract readonly class EntityService
{
    /**
     * EntityService constructor.
     */
    public function __construct(
        private PDO                        $pdo,
        protected CaseConverter            $caseConverter,
        private QueryBuilder               $queryBuilder,
        protected EntityAttributeProcessor $entityAttributeProcessor
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
     * TODO: Support DateTime fields
     *
     * @param array<string, mixed> $data
     * @param string $entityClass
     * @return object
     * @throws Exception
     */
    public function mapResultToEntity(array $data, string $entityClass): object
    {
        $entityProps = [];
        $mappedData = [];
        foreach ($data as $field => &$value) {
            $camelCaseField = $this->caseConverter->snakeCaseToCamelCase($field);
            $mappedData[$camelCaseField] = &$value;
        }

        foreach ($this->getEntityPropertyInfo($entityClass) as $propName => $propType) {
            /** @var ReflectionNamedType $propType */
            if (is_a($propType->getName(), DateTime::class, true)) {
                $entityProps[$propName] = new DateTime($mappedData[$propName]) ?? null;
            } else {
                $entityProps[$propName] = $mappedData[$propName] ?? null;
            }
        }

        return new $entityClass(...$entityProps);
    }

    /**
     * @param object $entity
     * @return void
     *
     * Currently requires getters and setters for all entity fields.
     * TODO: Handle values set by database. Refresh if attribute for that is set on any property of entity class?
     */
    public function saveAsNew(object $entity): void
    {
        try {
            $data = $this->getFieldValueMap($entity);
            $insertQuery = $this->queryBuilder->buildInsertQuery($entity::class);
            $stmt = $this->pdo->prepare($insertQuery);
            $stmt->execute($data);
            $idField = $this->entityAttributeProcessor->getEntityIdFieldName($entity::class);
            if ($idField && $idVal = $this->pdo->lastInsertId()) {
                $entity->{"set$idField"}((int)$idVal);
            }
        } catch (Throwable $t) {
            throw new PersistenceException('Failed to save new entity: ' . $entity::class, $t);
        }
    }

    public function update(object $entity): void
    {
        try {
            $idField = $this->entityAttributeProcessor->getEntityIdFieldName($entity::class);
            $data = $this->getFieldValueMap($entity);

            if ($entity->{"get$idField"}() === null) {
                throw new PersistenceException('Cannot update entity without id set');
            }
            $query = $this->queryBuilder->buildUpdateQuery($entity::class);
            $stmt = $this->pdo->prepare($query);
            $stmt->execute($data);
            // potential re-sync with db for db-generated values?
        } catch (Throwable $t) {
            throw new PersistenceException('Failed to update entity: ' . $entity::class, $t);
        }
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
        $success = $stmt->execute($parameters);
        $result = [];
        if (!$success) {
            return $result;
        }
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $result[] = $this->mapResultToEntity($row, $mappingClass);
        }

        return $result;
    }

    private function getEntityPropertyInfo(string $entityClass): array
    {
        $refClass = new ReflectionClass($entityClass);
        $result = [];
        $i = 0;
        foreach ($refClass->getProperties() as $refProperty) {
            $result[$refProperty->getName() ?? $i++] = $refProperty->getType();
        }
        return $result;
    }

    private function getFieldValueMap(object $entity): array
    {
        $data = [];
        $propertyNames = $this->getEntityPropertyInfo($entity::class);
        foreach ($propertyNames as $propName => $propType) {
            $dbField = $this->caseConverter->camelCaseToSnakeCase($propName);
            $propVal = $entity->{"get$propName"}();
            //Should probably make this reusable
            if ($propVal instanceof DateTime) {
                $propVal = $propVal->format('Y-m-d H:i:s');
            }
            $data[$dbField] = $propVal;
        }
        return $data;
    }
}