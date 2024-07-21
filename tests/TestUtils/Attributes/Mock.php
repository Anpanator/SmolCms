<?php

declare(strict_types=1);

namespace SmolCms\TestUtils\Attributes;


use Attribute;

#[Attribute]
class Mock
{

    /**
     * Mock constructor.
     * @param string $className
     */
    public function __construct(
        private string $className
    ) {
    }

    /**
     * @return string
     */
    public function getClassName(): string
    {
        return $this->className;
    }
}