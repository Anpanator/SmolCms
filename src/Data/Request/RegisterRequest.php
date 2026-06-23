<?php
declare(strict_types=1);

namespace SmolCms\Data\Request;

use SensitiveParameter;
use SmolCms\Service\Validation\Attribute\ValidateEmail;
use SmolCms\Service\Validation\Attribute\ValidateNotNull;
use SmolCms\Service\Validation\Attribute\ValidateStringSize;
use SmolCms\Service\Validation\Attribute\ValidateStringSizeBytes;

readonly class RegisterRequest extends ValidatedRequest
{
    public function __construct(
        Request        $rawRequest,
        #[ValidateStringSize(minLength: 1, maxLength: 255)]
        #[ValidateNotNull]
        #[ValidateEmail]
        public ?string $email,
        #[SensitiveParameter]
        #[ValidateStringSize(minLength: 10, maxLength: 72)]
        #[ValidateStringSizeBytes(max: 72)]
        #[ValidateNotNull]
        public ?string $password,
        #[SensitiveParameter]
        #[ValidateStringSize(minLength: 10, maxLength: 72)]
        #[ValidateNotNull]
        public ?string $passwordRepeat,
        #[ValidateStringSize(minLength: 1, maxLength: 69)]
        #[ValidateNotNull]
        public ?string $displayName,
        #[ValidateStringSize(minLength: 1, maxLength: 255)]
        #[ValidateNotNull]
        public ?string $loginName,
    )
    {
        parent::__construct($rawRequest);
    }
}
