<?php

declare(strict_types=1);

namespace SmolCms\Service\Validation\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class ValidateNotNull implements PropertyValidationAttribute
{
    /**
     * @inheritDoc
     */
    public function validate(mixed $value, bool $nullable = false): bool
    {
        return $value !== null;
    }
}