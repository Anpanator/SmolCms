<?php

declare(strict_types=1);

namespace SmolCms\Test\Unit\Service\Validation\Attribute;

use SmolCms\Service\Validation\Attribute\ValidateRange;

class ValidateRangeTest extends PropertyValidationAttributeTest
{
    public function testValidate_successValueInRange()
    {
        $validateRange = new ValidateRange(min: PHP_FLOAT_MIN, max: PHP_FLOAT_MAX);
        self::assertTrue($validateRange->validate(1));
    }

    public function testValidate_failureValueNotInRange()
    {
        $validateRange = new ValidateRange(min: PHP_FLOAT_MIN, max: 0);
        self::assertFalse($validateRange->validate(1));
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->propertyValidationAttribute = new ValidateRange(0, 0);
    }
}
