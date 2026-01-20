<?php

declare(strict_types=1);

namespace SmolCms\Service\Validation\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
readonly class ValidateAllowList implements PropertyValidationAttribute
{
    /**
     * ValidateAllowList constructor.
     * @param array $allowValues
     */
    public function __construct(
        private array $allowValues = []
    ) {
    }

    /**
     * @inheritDoc
     */
    public function validate(mixed $value): bool
    {
        if ($value === null) {
            return true;
        }
        return in_array($value, $this->allowValues, true);
    }

    public function getErrorMessage(): string
    {
        return 'Value must be one of: ' . implode(', ', $this->allowValues);
    }
}