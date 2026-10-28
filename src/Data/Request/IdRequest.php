<?php

declare(strict_types=1);

namespace SmolCms\Data\Request;

use SmolCms\Data\Request\Attribute\FromQuery;
use SmolCms\Service\Validation\Attribute\ValidateIntegerString;
use SmolCms\Service\Validation\Attribute\ValidateNotNull;

readonly class IdRequest extends ValidatedRequest
{
    public function __construct(
        Request $rawRequest,
        #[FromQuery]
        #[ValidateNotNull]
        #[ValidateIntegerString(min: 1, max: PHP_INT_MAX)]
        public ?string $id,
    ) {
        parent::__construct($rawRequest);
    }
}
