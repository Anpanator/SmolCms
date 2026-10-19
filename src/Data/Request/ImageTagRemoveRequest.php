<?php

declare(strict_types=1);

namespace SmolCms\Data\Request;

use SmolCms\Service\Validation\Attribute\ValidateNotNull;
use SmolCms\Service\Validation\Attribute\ValidateRange;
use SmolCms\Service\Validation\Attribute\ValidateStringSize;

readonly class ImageTagRemoveRequest extends ValidatedRequest
{
    public function __construct(
        Request $rawRequest,
        #[ValidateNotNull]
        #[ValidateRange(min: 1, max: PHP_INT_MAX)]
        public ?int $imageId,
        #[ValidateNotNull]
        #[ValidateStringSize(minLength: 1, maxLength: 100)]
        public ?string $tag,
    )
    {
        parent::__construct($rawRequest);
    }
}
