<?php

declare(strict_types=1);

namespace SmolCms\Data\Request;

use SmolCms\Data\Constant\TagInputLimits;
use SmolCms\Service\Validation\Attribute\ValidateNotNull;
use SmolCms\Service\Validation\Attribute\ValidateRange;
use SmolCms\Service\Validation\Attribute\ValidateMaxTagCount;
use SmolCms\Service\Validation\Attribute\ValidateStringSize;

readonly class ImageTagsRequest extends ValidatedRequest
{
    public function __construct(
        Request $rawRequest,
        #[ValidateNotNull]
        #[ValidateRange(min: 1, max: PHP_INT_MAX)]
        public ?int $imageId,
        #[ValidateNotNull]
        #[ValidateStringSize(minLength: 1, maxLength: TagInputLimits::MAX_TAG_LIST_LENGTH)]
        #[ValidateMaxTagCount(maxCount: TagInputLimits::MAX_TAG_COUNT)]
        public ?string $tags,
    )
    {
        parent::__construct($rawRequest);
    }
}
