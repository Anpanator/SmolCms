<?php

declare(strict_types=1);

namespace SmolCms\TestUtils\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class Stub
{

    public function __construct(
        private string $className
    )
    {
    }

    public function getClassName(): string
    {
        return $this->className;
    }
}
