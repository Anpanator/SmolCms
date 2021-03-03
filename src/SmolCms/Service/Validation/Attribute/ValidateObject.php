<?php

declare(strict_types=1);

namespace SmolCms\Service\Validation\Attribute;

use Attribute;

/**
 * Class ValidateObject
 * @package SmolCms\Service\Validation\Attribute
 *
 * Marker attribute to indicate nested object validation.
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
class ValidateObject implements ValidationAttribute
{

}