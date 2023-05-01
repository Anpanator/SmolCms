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

    public function validate(mixed $value, bool $nullable = false): bool
    {
        return ($nullable && $value === null) || ($value !== null && strlen($value) <= $this->max);
    }
}