<?php

declare(strict_types=1);

namespace SmolCms\Data\Request;

use SmolCms\Data\Request\Attribute\FromQuery;
use SmolCms\Service\Validation\Attribute\ValidateNotNull;
use SmolCms\Service\Validation\Attribute\ValidateRegex;

readonly class ImageFileRequest extends ValidatedRequest
{
    public function __construct(
        Request $rawRequest,
        #[FromQuery]
        #[ValidateNotNull]
        #[ValidateRegex('/^[a-f0-9]{64}\\.(avif|webp|jpeg|png)$/')]
        public ?string $name,
    ) {
        parent::__construct($rawRequest);
    }
}
