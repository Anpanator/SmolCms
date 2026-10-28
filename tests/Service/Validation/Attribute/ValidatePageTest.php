<?php

declare(strict_types=1);

namespace SmolCms\Test\Service\Validation\Attribute;

use SmolCms\Service\Validation\Attribute\ValidatePage;

class ValidatePageTest extends PropertyValidationAttributeTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->propertyValidationAttribute = new ValidatePage(itemsPerPage: 25);
    }

    public function testValidateAcceptsPositivePageWithinOffsetRange(): void
    {
        $maxPage = intdiv(PHP_INT_MAX, 25) + 1;

        self::assertTrue($this->propertyValidationAttribute->validate('1'));
        self::assertTrue($this->propertyValidationAttribute->validate((string)$maxPage));
    }

    public function testValidateRejectsInvalidPageValues(): void
    {
        $maxPage = intdiv(PHP_INT_MAX, 25) + 1;

        self::assertFalse($this->propertyValidationAttribute->validate('0'));
        self::assertFalse($this->propertyValidationAttribute->validate('1.5'));
        self::assertFalse($this->propertyValidationAttribute->validate('abc'));
        self::assertFalse($this->propertyValidationAttribute->validate((string)($maxPage + 1)));
    }

    public function testGetErrorMessageDescribesOffsetLimit(): void
    {
        self::assertSame(
            'Page must be a positive integer small enough for 25 items per page.',
            $this->propertyValidationAttribute->getErrorMessage(),
        );
    }
}
