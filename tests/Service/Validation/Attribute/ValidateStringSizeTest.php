<?php
declare(strict_types=1);

namespace SmolCms\Test\Service\Validation\Attribute;

use SmolCms\Service\Validation\Attribute\ValidateStringSize;
use function PHPUnit\Framework\assertFalse;
use function PHPUnit\Framework\assertTrue;


class ValidateStringSizeTest extends PropertyValidationAttributeTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->propertyValidationAttribute = new ValidateStringSize(0, 0);
    }

    public function testValidate_successWithMultiByteCharacter()
    {
        $testee = new ValidateStringSize(minLength: 2, maxLength: 2);
        $testString = "热茶";
        $result = $testee->validate($testString);
        assertTrue($result);
    }

    public function testValidate_success()
    {
        $testee = new ValidateStringSize(maxLength: 10);
        $testString = "1234567890";
        $result = $testee->validate($testString);
        assertTrue($result);
    }

    public function testValidate_successMaxLengthNull()
    {
        $testee = new ValidateStringSize(maxLength: null);
        $testString = "1234567890";
        $result = $testee->validate($testString);
        assertTrue($result);
    }

    public function testValidate_successEmptyString()
    {
        $testee = new ValidateStringSize(minLength: 0, maxLength: 0);
        $testString = "";
        $result = $testee->validate($testString);
        assertTrue($result);
    }

    public function testValidate_failureStringTooLong()
    {
        $testee = new ValidateStringSize(minLength: 0, maxLength: 5);
        $testString = "123456";
        $result = $testee->validate($testString);
        assertFalse($result);
    }

    public function testValidate_failureStringTooShort()
    {
        $testee = new ValidateStringSize(minLength: 2, maxLength: 5);
        $testString = "1";
        $result = $testee->validate($testString);
        assertFalse($result);
    }
}
