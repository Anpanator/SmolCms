<?php
declare(strict_types=1);

namespace SmolCms\Data\DTO;

readonly class SessionUserData
{

    public function __construct(
        public int    $id,
        public string $displayName,
    )
    {
    }
}