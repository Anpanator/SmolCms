<?php
declare(strict_types=1);

namespace SmolCms\Service\DB;

use ReflectionClass;
use SmolCms\Service\Core\CaseConverter;

class QueryBuilder
{
    // Used when syntactically a limit is needed, but we don't want one
    private const NO_LIMIT_NUM = PHP_INT_MAX;

    public function __construct(
        private readonly CaseConverter $caseConverter,
        private readonly EntityAttributeProcessor $entityAttributeProcessor
    )
    {
    }

    public function buildQuery(QueryCriteria $qc): string
    {
        $mainEntity = $qc->getMainEntity();
        $table = $this->entityAttributeProcessor->getEntityTableName($mainEntity);

        $queryParts = [$qc->getType()];

        // Add field names for select
        if ($qc->getType() === QueryCriteria::TYPE_SELECT) {
            $queryParts[] = implode(', ', $this->getEntityFields($mainEntity));
        }
        if ($qc->getType() === QueryCriteria::TYPE_SELECT || $qc->getType() === QueryCriteria::TYPE_DELETE) {
            $queryParts[] = 'FROM';
        }
        $queryParts[] = $table;

        if ($qc->getType() === QueryCriteria::TYPE_UPDATE) {
            $queryParts[] = 'SET';
            $fields = $this->getEntityFields($qc->getMainEntity());
            $fieldPart = [];
            foreach ($fields as $field) {
                $fieldPart[] = "$field = :$field";
            }
            $queryParts[] = implode(',', $fieldPart);
        }
        $whereParts = $this->buildWhereParts($qc);
        if (!empty($whereParts)) {
            $queryParts[] = 'WHERE 1';
            array_push($queryParts, ...$whereParts);
        }

        if ($qc->getLimit() !== null || $qc->getOffset() !== null) {
            $queryParts[] = 'LIMIT';
            if ($qc->getOffset() !== null) {
                $queryParts[] = $qc->getOffset() . ',';
            }
            $queryParts[] = $qc->getLimit() ?? self::NO_LIMIT_NUM;
        }

        return implode(' ', $queryParts);
    }

    public function buildInsertQuery(string $entityClass): string
    {
        $tableName = $this->entityAttributeProcessor->getEntityTableName($entityClass);
        $fields = $this->getEntityFields($entityClass);
        $query = "INSERT INTO $tableName ("
            . implode(', ', $fields) . ') VALUES (:' . implode(', :', $fields)
            . ')';

        return $query;
    }

    private function buildWhereParts(QueryCriteria $qc): array
    {
        $parts = [];
        foreach ($qc->getWhereConditions() as $condition) {
            if (isset($condition[QueryCriteria::KEY_AND])) {
                $parts[] = 'AND';
                $parts[] = $condition[QueryCriteria::KEY_AND];
            } else if (isset($condition[QueryCriteria::KEY_OR])) {
                $parts[] = 'OR';
                $parts[] = $condition[QueryCriteria::KEY_OR];
            }
        }
        return $parts;
    }

    private function getEntityFields(string $entityClass): array
    {
        $refEntity = new ReflectionClass($entityClass);
        $refProperties = $refEntity->getProperties();
        $fields = [];
        foreach ($refProperties as $property) {
            $fields[] = $this->caseConverter->camelCaseToSnakeCase($property->getName());
        }
        return $fields;
    }
}
