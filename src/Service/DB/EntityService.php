<?php
declare(strict_types=1);

namespace SmolCms\Service\DB;


use BackedEnum;
use DateTime;
use Exception;
use PDO;
use PDOStatement;
use ReflectionClass;
use ReflectionEnum;
use ReflectionException;
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
        protected PDO                      $pdo,
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
            if (is_a($propType->getName(), DateTime::class, true) && isset($mappedData[$propName])) {
                $entityProps[$propName] = new DateTime($mappedData[$propName]) ?? null;
            } elseif (is_a($propType->getName(), BackedEnum::class, true) && isset($mappedData[$propName])) {
                $enumValue = $mappedData[$propName];
                $enumReflection = new ReflectionEnum($propType->getName());
                if ($enumReflection->getBackingType()->getName() === 'int') {
                    $enumValue = (int)$enumValue;
                }
                $entityProps[$propName] = $propType->getName()::from($enumValue);
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
            self::bindParameters($stmt, $data);
            $stmt->execute();
            $idField = $this->entityAttributeProcessor->getEntityIdFieldName($entity::class);
            if ($idField && $idVal = $this->pdo->lastInsertId()) {
                $entity->{"set$idField"}((int)$idVal);
            }
        } catch (Throwable $t) {
            throw new PersistenceException('Failed to save new entity: ' . $entity::class, $t);
        }
    }

    public function saveOrUpdate(object $entity): void
    {
        $idField = $this->entityAttributeProcessor->getEntityIdFieldName($entity::class);
        if ($idField === null) {
            throw new PersistenceException('Entity does not have an ID field. Missing attribute?');
        }
        if ($entity->{"get$idField"}() !== null) {
            $this->update($entity);
        } else {
            $this->saveAsNew($entity);
        }
    }

    /**
     * Bulk save: persists every entity through the regular save mechanism within a
     * single transaction, so either all or none of the entities are written. Joins
     * an already open transaction instead of nesting.
     *
     * @param array<object> $entities
     */
    public function saveAll(array $entities): void
    {
        if ($entities === []) {
            return;
        }
        foreach ($entities as $entity) {
            if (!is_object($entity)) {
                throw new PersistenceException('saveAll() only accepts entity objects.');
            }
        }
        $this->withinTransaction(function () use ($entities): void {
            foreach ($entities as $entity) {
                $this->saveOrUpdate($entity);
            }
        });
    }

    public function update(object $entity): void
    {
        try {
            $idField = $this->entityAttributeProcessor->getEntityIdFieldName($entity::class);
            $data = $this->getFieldValueMap($entity);

            if ($entity->{"get$idField"}() === null) {
                throw new PersistenceException('Cannot update entity without id set');
            }

            $qc = new QueryCriteria();
            $qc->update($entity::class)
                ->andWhere("$idField = :$idField")
                ->withParameters($data);

            $query = $this->queryBuilder->buildQuery($qc);
            $stmt = $this->pdo->prepare($query);
            self::bindParameters($stmt, $qc->getParameters());
            $stmt->execute();
            // TODO: potential re-sync with db for db-generated values?
        } catch (Throwable $t) {
            throw new PersistenceException('Failed to update entity: ' . $entity::class, $t);
        }
    }

    /**
     * Use this if you want to run a particular query multiple times with different values.
     * You'll get a PDOStatement with the criteria's parameters already bound, so you can
     * update them manually or simply re-execute it if needed.
     *
     * @param QueryCriteria $qc
     * @return PDOStatement
     */
    protected function prepare(QueryCriteria $qc): PDOStatement
    {
        $query = $this->queryBuilder->buildQuery($qc);
        $stmt = $this->pdo->prepare($query);
        self::bindParameters($stmt, $qc->getParameters());
        return $stmt;
    }

    /**
     * Counts the rows matching a SELECT criteria. Limit, offset and ordering of the
     * criteria do not affect the count.
     */
    protected function countResults(QueryCriteria $qc): int
    {
        $stmt = $this->pdo->prepare($this->queryBuilder->buildCountQuery($qc));
        self::bindParameters($stmt, $qc->getParameters());
        $stmt->execute();
        return (int)$stmt->fetchColumn();
    }

    /**
     * Runs the operation inside a transaction. Joins an already open transaction instead
     * of nesting; only the caller that opened the transaction commits or rolls back.
     *
     * @param callable(): mixed $operation
     * @return mixed
     */
    protected function withinTransaction(callable $operation): mixed
    {
        $ownsTransaction = !$this->pdo->inTransaction();
        if ($ownsTransaction) {
            $this->pdo->beginTransaction();
        }

        try {
            $result = $operation();
            if ($ownsTransaction) {
                $this->pdo->commit();
            }
            return $result;
        } catch (Throwable $throwable) {
            if ($ownsTransaction && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $throwable;
        }
    }

    /**
     * @param QueryCriteria $qc
     * @return array<object>|null SELECT always returns an array, DELETE returns null
     */
    protected function execute(QueryCriteria $qc): ?array
    {
        $stmt = $this->prepare($qc);
        if ($qc->getType() === QueryCriteria::TYPE_SELECT) {
            return $this->fetchResults($stmt, $qc->getMainEntity());
        }
        $stmt->execute();
        return null;
    }

    protected function fetchResults(PDOStatement $stmt, string $mappingClass): array
    {
        $success = $stmt->execute();
        $result = [];
        if (!$success) {
            return $result;
        }
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $result[] = $this->mapResultToEntity($row, $mappingClass);
        }

        return $result;
    }

    /**
     * Binds parameters with their PHP-native types, so comparisons against untyped
     * expressions (e.g. COUNT(*)) receive integers instead of strings.
     *
     * @param array<string, mixed> $parameters
     */
    private static function bindParameters(PDOStatement $stmt, array $parameters): void
    {
        foreach ($parameters as $name => $value) {
            $type = match (true) {
                is_int($value) => PDO::PARAM_INT,
                is_bool($value) => PDO::PARAM_BOOL,
                is_null($value) => PDO::PARAM_NULL,
                default => PDO::PARAM_STR,
            };
            $stmt->bindValue(":$name", $value, $type);
        }
    }

    /**
     * @param string $entityClass
     * @return array<string, ReflectionNamedType>
     * @throws ReflectionException
     */
    private function getEntityPropertyInfo(string $entityClass): array
    {
        $refClass = new ReflectionClass($entityClass);
        $result = [];
        foreach ($refClass->getProperties() as $refProperty) {
            $result[$refProperty->getName()] = $refProperty->getType();
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
            if ($propVal instanceof BackedEnum) {
                $propVal = $propVal->value;
            }
            $data[$dbField] = $propVal;
        }
        return $data;
    }
}
