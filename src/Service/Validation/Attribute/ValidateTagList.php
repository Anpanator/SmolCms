<?php

declare(strict_types=1);

namespace SmolCms\Service\Validation\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
final readonly class ValidateTagList implements PropertyValidationAttribute
{
    public function validate(mixed $value): bool
    {
        if ($value === null) {
            return true;
        }
        if (!is_string($value)) {
            return false;
        }

        foreach (explode(',', $value) as $tag) {
            if (trim($tag) !== '') {
                return true;
            }
        }
        return false;
    }

    public function getErrorMessage(): string
    {
        return 'Tag list must contain at least one non-empty tag.';
    }
}
