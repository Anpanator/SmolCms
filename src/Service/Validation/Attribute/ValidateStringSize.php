<?php
declare(strict_types=1);

namespace SmolCms\Service\Validation\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
readonly class ValidateStringSize implements PropertyValidationAttribute
{

    public function __construct(
        private int  $minLength = 0,
        private ?int $maxLength = null
    )
    {
    }

    public function validate(mixed $value): bool
    {
        if ($value === null) {
            return true;
        }
        if (!is_string($value)) {
            return false;
        }
        if (mb_strlen($value) < $this->minLength) {
            return false;
        }
        if ($this->maxLength !== null && mb_strlen($value) > $this->maxLength) {
            return false;
        }
        return true;
    }

    public function getErrorMessage(): string
    {
        $message = "String value must have at least {$this->minLength} characters";
        if ($this->maxLength !== null) {
            $message .= " and no more than {$this->maxLength} characters";
        }
        return $message;
    }
}