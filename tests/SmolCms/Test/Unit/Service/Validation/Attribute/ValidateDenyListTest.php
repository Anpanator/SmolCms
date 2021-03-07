<?php

declare(strict_types=1);

namespace SmolCms\Test\Unit\Service\Validation\Attribute;

use SmolCms\Service\Validation\Attribute\ValidateDenyList;

class ValidateDenyListTest extends PropertyValidationAttributeTest
{
    public function testValidate_successWithNoDeniedValue()
    {
        $validateDenyList = new ValidateDenyList(['DENIED1', false, "DENIED3"]);
        $result = $validateDenyList->validate('ALLOWED VALUE');
        self::assertTrue($result);
    }

    public function testValidate_failureWithDeniedValue()
    {
        $validateDenyList = new ValidateDenyList(['DENIED1', false, "DENIED3"]);
        $result = $validateDenyList->validate(false);
        self::assertFalse($result);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->propertyValidationAttribute = new ValidateDenyList([]);
    }
}
