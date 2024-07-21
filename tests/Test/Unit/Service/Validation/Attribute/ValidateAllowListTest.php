<?php

declare(strict_types=1);

namespace SmolCms\Test\Unit\Service\Validation\Attribute;

use SmolCms\Service\Validation\Attribute\ValidateAllowList;

class ValidateAllowListTest extends PropertyValidationAttributeTest
{
    public function testValidate_successWithAllowedValue()
    {
        $validateDenyList = new ValidateAllowList(['ALLOWED1', true, "ALLOWED3", 3]);
        $result = $validateDenyList->validate(3);
        self::assertTrue($result);
    }

    public function testValidate_failureWithNotAllowedValue()
    {
        $validateDenyList = new ValidateAllowList(['ALLOWED1', true, "ALLOWED3", 3]);
        $result = $validateDenyList->validate(9999);
        self::assertFalse($result);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->propertyValidationAttribute = new ValidateAllowList([]);
    }
}
