<?php

declare(strict_types=1);

namespace SmolCms\Service\Validation\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class ValidateDenyList implements PropertyValidationAttribute
{
    /**
     * ValidateDenyList constructor.
     * @param array $denyValues
     */
    public function __construct(
        private array $denyValues = []
    ) {
    }

    /**
     * @inheritDoc
     */
    public function validate(mixed $value, bool $nullable = false): bool
    {
        return ($value === null && $nullable) || ($value !== null && !in_array($value, $this->denyValues, true));
    }

}