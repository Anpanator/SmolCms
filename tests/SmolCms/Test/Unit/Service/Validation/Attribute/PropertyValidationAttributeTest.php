<?php

declare(strict_types=1);

namespace SmolCms\Test\Unit\Service\Validation\Attribute;


use SmolCms\Service\Validation\Attribute\PropertyValidationAttribute;
use SmolCms\TestUtils\SimpleTestCase;

abstract class PropertyValidationAttributeTest extends SimpleTestCase
{
    protected PropertyValidationAttribute $propertyValidationAttribute;

    public function testValidate_successNullableTrueValueNull() {
        $result = $this->propertyValidationAttribute->validate(null, true);
        self::assertTrue($result, 'Validation should succeed with value null and nullable true');
    }

    public function testValidate_FailureNullableFalseValueNull() {
        $result = $this->propertyValidationAttribute->validate(null, false);
        self::assertFalse($result, 'Validation should fail with value null and nullable false');
    }
}