<?php

declare(strict_types=1);

namespace SmolCms\Data\Persistence;

use DateTime;
use SmolCms\Service\DB\Attribute\Entity;
use SmolCms\Service\DB\Attribute\Id;

#[Entity(table: 'tag_approval')]
class TagApprovalEntity
{
    public function __construct(
        #[Id]
        private ?int $id,
        private int $tagId,
        private int $userId,
        private DateTime $created,
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

    public function getTagId(): int
    {
        return $this->tagId;
    }

    public function setTagId(int $tagId): void
    {
        $this->tagId = $tagId;
    }

    public function getUserId(): int
    {
        return $this->userId;
    }

    public function setUserId(int $userId): void
    {
        $this->userId = $userId;
    }

    public function getCreated(): DateTime
    {
        return $this->created;
    }

    public function setCreated(DateTime $created): void
    {
        $this->created = $created;
    }
}
