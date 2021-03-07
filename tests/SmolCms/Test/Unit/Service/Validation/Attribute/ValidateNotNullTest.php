<?php

declare(strict_types=1);

namespace SmolCms\Test\Unit\Service\Validation\Attribute;

use SmolCms\Service\Validation\Attribute\ValidateNotNull;
use SmolCms\TestUtils\SimpleTestCase;

class ValidateNotNullTest extends SimpleTestCase
{
    private ValidateNotNull $validateNotNull;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validateNotNull = new ValidateNotNull();
    }


    public function testValidate_successNonNullValue()
    {
        self::assertTrue($this->validateNotNull->validate("NOT NULL"));
    }

    public function testValidate_successNullValue()
    {
        self::assertFalse($this->validateNotNull->validate(null));
    }
}
