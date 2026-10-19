<?php

declare(strict_types=1);

namespace SmolCms\Data\Persistence;

use SmolCms\Service\DB\Attribute\Entity;
use SmolCms\Service\DB\Attribute\Id;

#[Entity(table: 'tag_category')]
class TagCategoryEntity
{
    public function __construct(
        #[Id]
        private ?int   $id,
        private string $name,
    )
    {
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(?int $id): void
    {
        $this->id = $id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }
}
