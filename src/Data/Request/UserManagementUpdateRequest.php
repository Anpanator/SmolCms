<?php

declare(strict_types=1);

namespace SmolCms\Data\Request;

use SmolCms\Service\Validation\Attribute\ValidateAllowList;
use SmolCms\Service\Validation\Attribute\ValidateNotNull;
use SmolCms\Service\Validation\Attribute\ValidateStringSize;

readonly class UserManagementUpdateRequest extends ValidatedRequest
{
    public function __construct(
        Request $rawRequest,
        #[ValidateNotNull]
        #[ValidateStringSize(minLength: 1, maxLength: 69)]
        public ?string $displayName,
        #[ValidateNotNull]
        #[ValidateAllowList(allowValues: [100, 200, 300, 400, 500, 600])]
        public ?int $accessLevel,
    )
    {
        parent::__construct($rawRequest);
    }
}
