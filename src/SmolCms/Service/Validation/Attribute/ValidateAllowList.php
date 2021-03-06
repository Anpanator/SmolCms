<?php

declare(strict_types=1);

namespace SmolCms\Service\Validation\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class ValidateAllowList implements PropertyValidationAttribute
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
    public function validate(mixed $value, bool $nullable = false): bool
    {
        return ($value === null && $nullable) || ($value !== null && in_array($value, $this->allowValues, true));
    }

}