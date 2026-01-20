<?php

declare(strict_types=1);

namespace SmolCms\Service\Validation\Attribute;

use SmolCms\Service\Validation\Validator;

/**
 * Interface ValidationAttribute
 * @package SmolCms\Service\Validation\Attribute
 *
 * Interface for any attribute that supports property validation.
 * @see Validator
 */
interface PropertyValidationAttribute extends ValidationAttribute
{
    /**
     * @param mixed $value The value to validate
     * @return bool
     */
    public function validate(mixed $value): bool;
}