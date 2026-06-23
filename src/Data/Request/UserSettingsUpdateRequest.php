<?php
declare(strict_types=1);

namespace SmolCms\Data\Request;

use SmolCms\Service\Validation\Attribute\ValidateNotNull;
use SmolCms\Service\Validation\Attribute\ValidateStringSize;

readonly class UserSettingsUpdateRequest extends ValidatedRequest
{
    public function __construct(
        Request        $rawRequest,
        #[ValidateStringSize(minLength: 1, maxLength: 69)]
        #[ValidateNotNull]
        public ?string $displayName,
    )
    {
        parent::__construct($rawRequest);
    }
}
