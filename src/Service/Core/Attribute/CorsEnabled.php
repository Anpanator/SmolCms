<?php

declare(strict_types=1);

namespace SmolCms\Service\Core\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
final readonly class CorsEnabled
{
}
