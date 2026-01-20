<?php

declare(strict_types=1);

namespace SmolCms\Service\Validation\Attribute;

use SmolCms\Service\Validation\Validator;

/**
 * Interface ValidationAttribute
 * @package SmolCms\Service\Validation\Attribute
 *
 * Marker interface for attributes used for validation
 * @see Validator
 */
interface ValidationAttribute
{
    public function getErrorMessage(): string;
}