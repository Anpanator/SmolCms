<?php

declare(strict_types=1);

namespace SmolCms\Data\Request;

use SmolCms\Data\Request\Attribute\FromQuery;

readonly class TagSuggestionSearchRequest extends ValidatedRequest
{
    public function __construct(
        Request $rawRequest,
        #[FromQuery]
        public ?string $q,
    ) {
        parent::__construct($rawRequest);
    }
}
