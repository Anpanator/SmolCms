<?php

declare(strict_types=1);

namespace SmolCms\Test\Service\Validation\Attribute;

use SmolCms\Service\Validation\Attribute\ValidateRegex;

class ValidateRegexTest extends PropertyValidationAttributeTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->propertyValidationAttribute = new ValidateRegex('/^[a-z]+$/');
    }

    public function testValidateAcceptsMatchingString(): void
    {
        self::assertTrue($this->propertyValidationAttribute->validate('abc'));
    }

    public function testValidateRejectsNonMatchingAndNonStringValues(): void
    {
        self::assertFalse($this->propertyValidationAttribute->validate('Abc'));
        self::assertFalse($this->propertyValidationAttribute->validate(['abc']));
    }

    public function testGetErrorMessageDescribesFormatValidation(): void
    {
        self::assertSame(
            'Value does not match the required format.',
            $this->propertyValidationAttribute->getErrorMessage(),
        );
    }
}
