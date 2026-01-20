<?php

declare(strict_types=1);

namespace SmolCms\Test\Service\Validation\Attribute;


use SmolCms\Service\Validation\Attribute\PropertyValidationAttribute;
use SmolCms\TestUtils\SimpleTestCase;

abstract class PropertyValidationAttributeTest extends SimpleTestCase
{
    protected PropertyValidationAttribute $propertyValidationAttribute;

    public function testValidate_successNullValueReturnsTrue()
    {
        $result = $this->propertyValidationAttribute->validate(null);
        self::assertTrue($result, 'Validation should succeed with value null');
    }
}