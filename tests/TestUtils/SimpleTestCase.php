<?php

declare(strict_types=1);

namespace SmolCms\TestUtils;


use PHPUnit\Framework\TestCase;
use ReflectionObject;
use SmolCms\TestUtils\Attributes\Mock;

require_once dirname(__DIR__, 2) . '/constants.php';

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
            /** @var Mock $mockAttribute */
            $mockAttribute = reset($attributes)->newInstance();
            $mock = $this->getMockBuilder($mockAttribute->getClassName())
                ->disableOriginalConstructor()
                ->getMock();

            $reflectionProperty->setValue($this, $mock);
        }
    }

}