<?php
declare(strict_types=1);

namespace SmolCms\Service\DB;


use ReflectionClass;
use ReflectionException;
use SmolCms\Service\Core\CaseConverter;

abstract class EntityService
{
    /**
     * EntityService constructor.
     */
    public function __construct(
        protected CaseConverter $caseConverter
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