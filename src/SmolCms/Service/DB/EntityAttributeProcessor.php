<?php
declare(strict_types=1);

namespace SmolCms\Service\DB;

use ReflectionClass;
use SmolCms\Service\DB\Attribute\Entity;
use SmolCms\Service\DB\Attribute\Id;

class EntityAttributeProcessor
{
    public function getEntityTableName(string $entityClass): string
    {
        $refEntity = new ReflectionClass($entityClass);
        $refAttribute = $refEntity->getAttributes(Entity::class)[0];
        /** @var Entity $attribute */
        $attribute = $refAttribute->newInstance();
        return $attribute->getTable();
    }

    public function getEntityIdFieldName(string $entityClass): ?string
    {
        $refEntity = new ReflectionClass($entityClass);
        $refProps = $refEntity->getProperties();
        foreach ($refProps as $refProp) {
            $attributeList = $refProp->getAttributes(Id::class);
            if (empty($attributeList)) {
                continue;
            }
            return $refProp->getName();
        }
        return null;
    }
}