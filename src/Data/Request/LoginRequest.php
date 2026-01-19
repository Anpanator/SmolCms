<?php
declare(strict_types=1);

namespace SmolCms\Data\Request;

use SensitiveParameter;
use SmolCms\Service\Validation\Attribute\ValidateNotNull;
use SmolCms\Service\Validation\Attribute\ValidateStringSize;
use SmolCms\Service\Validation\Attribute\ValidateStringSizeBytes;

readonly class LoginRequest extends ValidatedRequest
{
    public function __construct(
        Request       $rawRequest,
        #[ValidateStringSize(minLength: 1, maxLength: 255)]
        #[ValidateNotNull]
        public ?string $loginName,
        #[SensitiveParameter]
        #[ValidateStringSize(minLength: 10, maxLength: 72)]
        #[ValidateStringSizeBytes(max: 72)]
        #[ValidateNotNull]
        public ?string $password,
    )
    {
        parent::__construct($rawRequest);
    }
}