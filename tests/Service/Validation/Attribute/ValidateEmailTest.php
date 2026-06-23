<?php

declare(strict_types=1);

namespace SmolCms\Test\Service\Validation\Attribute;

use SmolCms\Service\Validation\Attribute\ValidateEmail;

class ValidateEmailTest extends PropertyValidationAttributeTest
{
    public function testValidate_successValidEmail()
    {
        $validateEmail = new ValidateEmail();
        self::assertTrue($validateEmail->validate('user@example.com'));
    }

    public function testValidate_successEmailWithPlus()
    {
        $validateEmail = new ValidateEmail();
        self::assertTrue($validateEmail->validate('user+tag@domain.co.uk'));
    }

    public function testValidate_failureMissingAtSymbol()
    {
        $validateEmail = new ValidateEmail();
        self::assertFalse($validateEmail->validate('not-an-email'));
    }

    public function testValidate_failureEmptyString()
    {
        $validateEmail = new ValidateEmail();
        self::assertFalse($validateEmail->validate(''));
    }

    public function testValidate_failureOnlyDomain()
    {
        $validateEmail = new ValidateEmail();
        self::assertFalse($validateEmail->validate('@domain.com'));
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->propertyValidationAttribute = new ValidateEmail();
    }
}
