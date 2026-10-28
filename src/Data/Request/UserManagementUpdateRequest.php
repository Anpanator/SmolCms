<?php

declare(strict_types=1);

namespace SmolCms\Data\Request;

use SmolCms\Data\Request\Attribute\FromQuery;
use SmolCms\Service\Validation\Attribute\ValidateAllowList;
use SmolCms\Service\Validation\Attribute\ValidateIntegerString;
use SmolCms\Service\Validation\Attribute\ValidateNotNull;
use SmolCms\Service\Validation\Attribute\ValidateStringSize;

readonly class UserManagementUpdateRequest extends ValidatedRequest
{
    public function __construct(
        Request $rawRequest,
        #[FromQuery]
        #[ValidateNotNull]
        #[ValidateIntegerString(min: 1, max: PHP_INT_MAX)]
        public ?string $id,
        #[ValidateNotNull]
        #[ValidateStringSize(minLength: 1, maxLength: 69)]
        public ?string $displayName,
        #[ValidateNotNull]
        #[ValidateAllowList(allowValues: [100, 200, 300, 400, 500, 600])]
        public ?int $accessLevel,
        #[ValidateStringSize(minLength: 1, maxLength: 255)]
        public ?string $loginName = null,
    )
    {
        parent::__construct($rawRequest);
    }
}
