<?php

declare(strict_types=1);

namespace SmolCms\Service\Validation\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
readonly class ValidateDenyList implements PropertyValidationAttribute
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
    public function validate(mixed $value): bool
    {
        return !in_array($value, $this->denyValues, true);
    }

    public function getErrorMessage(): string
    {
        return 'Value must not be one of: ' . implode(', ', $this->denyValues);
    }
}