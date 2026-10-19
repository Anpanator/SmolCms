<?php

declare(strict_types=1);

namespace SmolCms\Data\Persistence;

use DateTime;
use SmolCms\Service\DB\Attribute\Entity;
use SmolCms\Service\DB\Attribute\Id;

#[Entity(table: 'tag_suggestion')]
class TagSuggestionEntity
{
    public function __construct(
        #[Id]
        private ?int $id,
        private int $imageId,
        private string $tagName,
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

    public function getImageId(): int
    {
        return $this->imageId;
    }

    public function setImageId(int $imageId): void
    {
        $this->imageId = $imageId;
    }

    public function getTagName(): string
    {
        return $this->tagName;
    }

    public function setTagName(string $tagName): void
    {
        $this->tagName = $tagName;
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
