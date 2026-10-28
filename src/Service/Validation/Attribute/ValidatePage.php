<?php

declare(strict_types=1);

namespace SmolCms\Service\Validation\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
final readonly class ValidatePage implements PropertyValidationAttribute
{
    public function __construct(private int $itemsPerPage)
    {
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
        if ($digits === '') {
            return false;
        }

        $maxPage = (string)(intdiv(PHP_INT_MAX, $this->itemsPerPage) + 1);
        $lengthComparison = strlen($digits) <=> strlen($maxPage);
        return $lengthComparison < 0
            || ($lengthComparison === 0 && strcmp($digits, $maxPage) <= 0);
    }

    public function getErrorMessage(): string
    {
        return "Page must be a positive integer small enough for {$this->itemsPerPage} items per page.";
    }
}
