<?php

declare(strict_types=1);

namespace SmolCms\Data\Request\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_PARAMETER)]
final readonly class FromQuery
{
}
