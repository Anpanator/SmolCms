<?php

declare(strict_types=1);

namespace SmolCms\Test\Service\Validation\Attribute;

use SmolCms\Service\Validation\Attribute\ValidateTagList;

class ValidateTagListTest extends PropertyValidationAttributeTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->propertyValidationAttribute = new ValidateTagList();
    }

    public function testValidateAcceptsAtLeastOneNonEmptyTag(): void
    {
        self::assertTrue($this->propertyValidationAttribute->validate(', first, , second'));
    }

    public function testValidateRejectsListsWithoutTagsAndNonStringValues(): void
    {
        self::assertFalse($this->propertyValidationAttribute->validate(' , , '));
        self::assertFalse($this->propertyValidationAttribute->validate(['tag']));
    }

    public function testGetErrorMessageDescribesNonEmptyTagRequirement(): void
    {
        self::assertSame(
            'Tag list must contain at least one non-empty tag.',
            $this->propertyValidationAttribute->getErrorMessage(),
        );
    }
}
