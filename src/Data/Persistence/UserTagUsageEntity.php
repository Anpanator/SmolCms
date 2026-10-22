<?php

declare(strict_types=1);

namespace SmolCms\Data\Persistence;

use DateTime;
use SmolCms\Service\DB\Attribute\Entity;

#[Entity(table: 'user_tag_usage')]
class UserTagUsageEntity
{
    public function __construct(
        private int $userId,
        private int $tagId,
        private int $usageCount,
        private ?DateTime $lastUsedAt,
    )
    {
    }

    public function getUserId(): int
    {
        return $this->userId;
    }

    public function setUserId(int $userId): void
    {
        $this->userId = $userId;
    }

    public function getTagId(): int
    {
        return $this->tagId;
    }

    public function setTagId(int $tagId): void
    {
        $this->tagId = $tagId;
    }

    public function getUsageCount(): int
    {
        return $this->usageCount;
    }

    public function setUsageCount(int $usageCount): void
    {
        $this->usageCount = $usageCount;
    }

    public function getLastUsedAt(): ?DateTime
    {
        return $this->lastUsedAt;
    }

    public function setLastUsedAt(?DateTime $lastUsedAt): void
    {
        $this->lastUsedAt = $lastUsedAt;
    }
}
