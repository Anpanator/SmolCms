<?php

declare(strict_types=1);

namespace SmolCms\Service\Validation\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
final readonly class ValidateMaxTagCount implements PropertyValidationAttribute
{
    public function __construct(private int $maxCount)
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
        return substr_count($value, ',') + 1 <= $this->maxCount;
    }

    public function getErrorMessage(): string
    {
        return "Tag list must contain no more than {$this->maxCount} tags.";
    }
}
