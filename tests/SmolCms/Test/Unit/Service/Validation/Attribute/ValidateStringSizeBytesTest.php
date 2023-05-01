<?php
declare(strict_types=1);

namespace SmolCms\Test\Unit\Service\Validation\Attribute;

use SmolCms\Service\Validation\Attribute\ValidateStringSizeBytes;

class ValidateStringSizeBytesTest extends PropertyValidationAttributeTest
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->propertyValidationAttribute = new ValidateStringSizeBytes(0);
    }

    public function testValidate_successWithStringWithinLimit()
    {
        $testSubject = new ValidateStringSizeBytes(max: 5);
        $result = $testSubject->validate("1234A");
        self::assertTrue($result);
    }

    public function testValidate_failureWithStringTooLarge()
    {
        $testSubject = new ValidateStringSizeBytes(max: 5);
        $result = $testSubject->validate("1234AB");
        self::assertFalse($result);
    }

    public function testValidate_successWithUTF8StringWithinLimit()
    {
        $testSubject = new ValidateStringSizeBytes(max: 8);
        // 😀 = 4 byte character
        $result = $testSubject->validate("😀😀");
        self::assertTrue($result);
    }

    public function testValidate_failureWithUTF8StringTooLarge()
    {
        $testSubject = new ValidateStringSizeBytes(max: 7);
        // 😀 = 4 byte character
        $result = $testSubject->validate("😀😀");
        self::assertFalse($result);
    }
}
