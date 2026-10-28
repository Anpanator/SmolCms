<?php

declare(strict_types=1);

namespace SmolCms\Data\Request;

readonly class ImageUploadRequest extends ValidatedRequest
{
    public function __construct(
        Request $rawRequest,
        public ?array $files = null,
    ) {
        parent::__construct($rawRequest);
    }
}
