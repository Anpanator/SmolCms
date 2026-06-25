<?php
declare(strict_types=1);

namespace SmolCms\Service\Core\Attribute;

use Attribute;
use SmolCms\Data\Constant\AccessLevel;

#[Attribute(Attribute::TARGET_METHOD)]
readonly class Authenticated
{
    public function __construct(
        public AccessLevel $accessLevel,
    )
    {
    }
}
