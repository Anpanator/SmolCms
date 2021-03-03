<?php

declare(strict_types=1);

namespace SmolCms\Service\Validation\Attribute;

/**
 * Interface ValidationAttribute
 * @package SmolCms\Service\Validation\Attribute
 *
 * Interface for any attribute that supports property validation.
 * @see \SmolCms\Service\Validation\Validator
 */
interface PropertyValidationAttribute extends ValidationAttribute
{
    /**
     * @param mixed $value The value to validate
     * @return bool
     */
    public function validate(mixed $value): bool;
}