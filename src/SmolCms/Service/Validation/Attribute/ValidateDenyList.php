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

    public function validate(mixed $value): bool
    {
        return !in_array($value, $this->denyValues, true);
    }

}