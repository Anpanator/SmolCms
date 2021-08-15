<?php
declare(strict_types=1);

namespace SmolCms\Service\DB;

use ReflectionClass;
use SmolCms\Service\Core\CaseConverter;
use SmolCms\Service\DB\Attribute\Entity;

class QueryBuilder
{
    // Used when syntactically a limit is needed, but we don't want one
    private const NO_LIMIT_NUM = PHP_INT_MAX;

    public function __construct(
        private CaseConverter $caseConverter
    )
    {
    }

    public function buildQuery(QueryCriteria $qc): string
    {
        $mainEntity = $qc->getMainEntity();
        $table = $this->getEntityTableName($mainEntity);

        $queryParts = [$qc->getType()];

        // Add field names for select
        if ($qc->getType() === QueryCriteria::TYPE_SELECT) {
            $queryParts[] = implode(', ', $this->getEntityFields($mainEntity));
        }

        if ($qc->getType() !== QueryCriteria::TYPE_UPDATE) {
            $queryParts[] = 'FROM';
        }

        $queryParts[] = $table;

        $queryParts[] = 'WHERE';
        $firstCondition = true;
        foreach ($qc->getWhereConditions() as $condition) {
            if (isset($condition[QueryCriteria::KEY_AND])) {
                if (!$firstCondition) $queryParts[] = 'AND';
                $queryParts[] = $condition[QueryCriteria::KEY_AND];
            } else if (isset($condition[QueryCriteria::KEY_OR])) {
                if (!$firstCondition) $queryParts[] = 'OR';
                $queryParts[] = $condition[QueryCriteria::KEY_OR];
            }
            $firstCondition = false;
        }


        if ($qc->getLimit() !== null || $qc->getOffset() !== null) {
            $queryParts[] = 'LIMIT';
            $queryParts[] = $qc->getOffset() ? $qc->getOffset() . ',' : '';
            $queryParts[] = $qc->getLimit() ?: self::NO_LIMIT_NUM;
        }

        return implode(' ', $queryParts);
    }

    private function getEntityTableName(string $entityClass): string
    {
        $refEntity = new ReflectionClass($entityClass);
        $refAttribute = $refEntity->getAttributes(Entity::class)[0];
        /** @var Entity $attribute */
        $attribute = $refAttribute->newInstance();
        return $attribute->getTable();
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