<?php
declare(strict_types=1);

namespace SmolCms\Service\Validation\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
readonly class ValidateStringSizeBytes implements PropertyValidationAttribute
{

    public function __construct(private int $max)
    {
    }

    public function validate(mixed $value): bool
    {
        if ($value === null) {
            return true;
        }
        return strlen($value) <= $this->max;
    }

    public function getErrorMessage(): string
    {
        return 'Value size must be at most ' . $this->max . ' bytes';
    }
}