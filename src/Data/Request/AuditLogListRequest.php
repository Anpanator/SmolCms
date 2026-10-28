<?php

declare(strict_types=1);

namespace SmolCms\Data\Request;

use SmolCms\Data\Request\Attribute\FromQuery;
use SmolCms\Service\Validation\Attribute\ValidatePage;

readonly class AuditLogListRequest extends ValidatedRequest
{
    public function __construct(
        Request $rawRequest,
        #[FromQuery]
        #[ValidatePage(itemsPerPage: 30)]
        public ?string $page,
        #[FromQuery]
        public ?string $q,
    ) {
        parent::__construct($rawRequest);
    }
}
