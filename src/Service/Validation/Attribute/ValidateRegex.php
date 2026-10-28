<?php

declare(strict_types=1);

namespace SmolCms\Service\Validation\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
final readonly class ValidateRegex implements PropertyValidationAttribute
{
    public function __construct(private string $pattern)
    {
    }

    public function validate(mixed $value): bool
    {
        if ($value === null) {
            return true;
        }
        if (!is_string($value)) {
            return false;
        }

        return preg_match($this->pattern, $value) === 1;
    }

    public function getErrorMessage(): string
    {
        return 'Value does not match the required format.';
    }
}
