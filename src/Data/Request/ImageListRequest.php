<?php

declare(strict_types=1);

namespace SmolCms\Data\Request;

use SmolCms\Data\Request\Attribute\FromQuery;
use SmolCms\Service\Validation\Attribute\ValidatePage;

readonly class ImageListRequest extends ValidatedRequest
{
    public function __construct(
        Request $rawRequest,
        #[FromQuery]
        #[ValidatePage(itemsPerPage: 25)]
        public ?string $page,
        #[FromQuery]
        public ?string $tags,
    ) {
        parent::__construct($rawRequest);
    }
}
