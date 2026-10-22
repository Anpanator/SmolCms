<?php

declare(strict_types=1);

namespace SmolCms\Data\Request;

use SmolCms\Service\Validation\Attribute\ValidateNotNull;

readonly class SetQuickTagsRequest extends ValidatedRequest
{
    public function __construct(
        Request $rawRequest,
        #[ValidateNotNull]
        public ?bool $enabled,
    )
    {
        parent::__construct($rawRequest);
    }
}
