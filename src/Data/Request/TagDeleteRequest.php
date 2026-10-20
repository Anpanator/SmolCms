<?php

declare(strict_types=1);

namespace SmolCms\Data\Request;

use SmolCms\Data\Constant\TagInputLimits;
use SmolCms\Service\Validation\Attribute\ValidateNotNull;
use SmolCms\Service\Validation\Attribute\ValidateStringSize;

readonly class TagDeleteRequest extends ValidatedRequest
{
    public function __construct(
        Request        $rawRequest,
        #[ValidateNotNull]
        #[ValidateStringSize(minLength: 1, maxLength: TagInputLimits::MAX_TAG_LENGTH)]
        public ?string $name,
    )
    {
        parent::__construct($rawRequest);
    }
}
