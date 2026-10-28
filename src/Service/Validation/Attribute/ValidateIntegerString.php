<?php

declare(strict_types=1);

namespace SmolCms\Service\Validation\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
final readonly class ValidateIntegerString implements PropertyValidationAttribute
{
    public function __construct(
        private int $min,
        private int $max,
    ) {
    }

    public function validate(mixed $value): bool
    {
        if ($value === null) {
            return true;
        }
        if (!is_string($value) || $value === '' || !ctype_digit($value)) {
            return false;
        }

        $digits = ltrim($value, '0');
        $digits = $digits === '' ? '0' : $digits;

        return self::compareUnsignedIntegers($digits, (string)$this->min) >= 0
            && self::compareUnsignedIntegers($digits, (string)$this->max) <= 0;
    }

    public function getErrorMessage(): string
    {
        return "Value must be an integer string between {$this->min} and {$this->max}.";
    }

    private static function compareUnsignedIntegers(string $left, string $right): int
    {
        return (strlen($left) <=> strlen($right)) ?: strcmp($left, $right);
    }
}
