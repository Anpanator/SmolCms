<?php

declare(strict_types=1);

namespace SmolCms\Service\Validation\Attribute;

use Attribute;

/**
 * Class ValidateHost
 * @package SmolCms\Service\Validation\Attribute
 *
 * Will check if a host is a valid value. By default, IPv4, IPv6 and domains are all allowed.
 * Pass the type to restrict to particular types. Types may be combined with bitwise OR operator.
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
class ValidateHost implements PropertyValidationAttribute
{
    public const ALLOW_ALL = PHP_INT_MAX;
    public const IPV4 = 1;
    public const IPV6 = 2;
    public const DOMAIN = 4;

    /**
     * ValidateHost constructor.
     * @param int $type
     */
    public function __construct(
        private int $type = self::ALLOW_ALL
    ) {
    }

    public function validate(mixed $value, bool $nullable = false): bool
    {
        if ($nullable && $value === null) {
            return true;
        }

        $isValid = false;
        if (($this->type & self::IPV4) === self::IPV4) {
            $isValid = $isValid || filter_var($value, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4);
        }

        if (($this->type & self::IPV6) === self::IPV6) {
            $isValid = $isValid || filter_var($value, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6);
        }

        if (($this->type & self::DOMAIN) === self::DOMAIN) {
            $isValid = $isValid || filter_var($value, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME);
        }
        return $isValid;
    }
}