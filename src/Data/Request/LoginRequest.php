<?php
declare(strict_types=1);

namespace SmolCms\Data\Request;

use SensitiveParameter;
use SmolCms\Service\Validation\Attribute\ValidateAllowList;

readonly class LoginRequest extends ValidatedRequest
{


    public function __construct(
        Request       $rawRequest,
        #[ValidateAllowList(['Anpan', 'Delulu'])]
        public string $username,
        #[SensitiveParameter]
        public string $password,
    )
    {
        parent::__construct($rawRequest);
    }
}