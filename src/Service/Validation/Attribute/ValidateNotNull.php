<?php

declare(strict_types=1);

namespace SmolCms\Service\Validation\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
readonly class ValidateNotNull implements PropertyValidationAttribute
{
    /**
     * @inheritDoc
     */
    public function validate(mixed $value): bool
    {
        return $value !== null;
    }

    public function getErrorMessage(): string
    {
        return 'Value cannot be null';
    }
}