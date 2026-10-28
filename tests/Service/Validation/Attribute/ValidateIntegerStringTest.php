<?php

declare(strict_types=1);

namespace SmolCms\Test\Service\Validation\Attribute;

use SmolCms\Service\Validation\Attribute\ValidateIntegerString;

class ValidateIntegerStringTest extends PropertyValidationAttributeTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->propertyValidationAttribute = new ValidateIntegerString(min: 1, max: 10);
    }

    public function testValidateAcceptsValueWithinRangeAndLeadingZeroes(): void
    {
        self::assertTrue($this->propertyValidationAttribute->validate('00010'));
    }

    public function testValidateRejectsZeroOutsideRange(): void
    {
        self::assertFalse($this->propertyValidationAttribute->validate('000'));
    }

    public function testValidateRejectsNonIntegerStringsAndOverflow(): void
    {
        self::assertFalse($this->propertyValidationAttribute->validate('-1'));
        self::assertFalse($this->propertyValidationAttribute->validate('1.5'));
        self::assertFalse($this->propertyValidationAttribute->validate('11'));
        self::assertFalse($this->propertyValidationAttribute->validate('999999999999999999999999'));
    }

    public function testGetErrorMessageDescribesConfiguredBounds(): void
    {
        self::assertSame(
            'Value must be an integer string between 1 and 10.',
            $this->propertyValidationAttribute->getErrorMessage(),
        );
    }
}
