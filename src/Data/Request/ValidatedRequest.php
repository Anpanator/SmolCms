<?php
declare(strict_types=1);

namespace SmolCms\Data\Request;

abstract readonly class ValidatedRequest
{
    public function __construct(
        public Request $rawRequest
    )
    {
    }
}