<?php
declare(strict_types=1);

namespace SmolCms\Service\DB\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class Entity
{
    public function __construct(
        private string $table,
    )
    {
    }

    public function getTable(): string
    {
        return $this->table;
    }
}