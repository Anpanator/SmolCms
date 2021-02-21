<?php

declare(strict_types=1);

namespace SmolCms\TestUtils;


use PHPUnit\Framework\TestCase;
use ReflectionNamedType;
use ReflectionObject;
use RuntimeException;
use SmolCms\TestUtils\Attributes\Mock;

class SimpleTestCase extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->initMocks();
    }

    /**
     * Reads the properties with the Mock attribute from the current test class instance
     * and fills them with an empty test double.
     */
    private function initMocks(): void
    {
        $reflector = new ReflectionObject($this);
        foreach ($reflector->getProperties() as $reflectionProperty) {
            $attributes = $reflectionProperty->getAttributes(Mock::class);
            if (!$attributes) {
                continue;
            }
            $reflectionType = $reflectionProperty->getType();
            if (!($reflectionType instanceof ReflectionNamedType) || $reflectionType->isBuiltin()) {
                throw new RuntimeException('Cannot mock untyped or scalar property');
            }
            $mock = $this->getMockBuilder($reflectionType->getName())
                ->disallowMockingUnknownTypes()
                ->disableOriginalConstructor()
                ->getMock();

            $reflectionProperty->setAccessible(true);
            $reflectionProperty->setValue($this, $mock);
        }
    }

}