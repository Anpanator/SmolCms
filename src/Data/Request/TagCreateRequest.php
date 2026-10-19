<?php

declare(strict_types=1);

namespace SmolCms\Data\Request;

use SmolCms\Service\Validation\Attribute\ValidateNotNull;

readonly class TagCreateRequest extends ValidatedRequest
{
    public function __construct(
        Request $rawRequest,
        #[ValidateNotNull]
        public ?string $name,
        public ?string $category,
    )
    {
        parent::__construct($rawRequest);
    }
}
