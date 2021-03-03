<?php

declare(strict_types=1);

namespace SmolCms\Service\Validation\Attribute;

use Attribute;

/**
 * Class ValidateRange
 * @package SmolCms\Service\Validation\Attribute
 *
 * Validates whether or not a value is numeric (int or float) and checks if it's in the supplied value range.
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
class ValidateRange implements PropertyValidationAttribute
{
    /**
     * ValidateRange constructor.
     * @param float $min
     * @param float $max
     */
    public function __construct(
        private float $min,
        private float $max
    ) {
    }

    /**
     * @inheritDoc
     */
    public function validate(mixed $value): bool
    {
        if (!is_int($value) && !is_float($value)) {
            return false;
        }
        return $value >= $this->min && $value <= $this->max;
    }

}