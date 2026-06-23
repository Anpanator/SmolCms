<?php
declare(strict_types=1);

namespace SmolCms\Service\Validation\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
readonly class ValidateEmail implements PropertyValidationAttribute
{
    public function validate(mixed $value): bool
    {
        if ($value === null) {
            return true;
        }
        return filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
    }

    public function getErrorMessage(): string
    {
        return 'Value must be a valid email address.';
    }
}
