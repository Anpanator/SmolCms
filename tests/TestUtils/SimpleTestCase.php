<?php

declare(strict_types=1);

namespace SmolCms\TestUtils;


use PHPUnit\Framework\TestCase;
use ReflectionObject;
use SmolCms\TestUtils\Attributes\Mock;
use SmolCms\TestUtils\Attributes\Stub;

require_once dirname(__DIR__, 2) . '/constants.php';

class SimpleTestCase extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->initTestDoubles();
    }

    /**
     * Reads the properties with the Mock or Stub attribute from the current test class instance
     * and fills them with an empty test double.
     */
    private function initTestDoubles(): void
    {
        $reflector = new ReflectionObject($this);
        foreach ($reflector->getProperties() as $reflectionProperty) {
            $mockAttributes = $reflectionProperty->getAttributes(Mock::class);
            if ($mockAttributes) {
                /** @var Mock $mockAttribute */
                $mockAttribute = reset($mockAttributes)->newInstance();
                $mock = $this->getMockBuilder($mockAttribute->getClassName())
                    ->disableOriginalConstructor()
                    ->getMock();

                $reflectionProperty->setValue($this, $mock);
                continue;
            }

            $stubAttributes = $reflectionProperty->getAttributes(Stub::class);
            if ($stubAttributes) {
                /** @var Stub $stubAttribute */
                $stubAttribute = reset($stubAttributes)->newInstance();
                $stub = $this->createStub($stubAttribute->getClassName());

                $reflectionProperty->setValue($this, $stub);
            }
        }
    }

}