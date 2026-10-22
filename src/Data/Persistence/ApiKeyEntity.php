<?php

declare(strict_types=1);

namespace SmolCms\Data\Persistence;

use DateTime;
use SmolCms\Service\DB\Attribute\Entity;
use SmolCms\Service\DB\Attribute\Id;

#[Entity(table: 'api_key')]
class ApiKeyEntity
{
    public function __construct(
        #[Id]
        private ?int $id,
        private int $userId,
        private string $keyHash,
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

    public function getUserId(): int
    {
        return $this->userId;
    }

    public function setUserId(int $userId): void
    {
        $this->userId = $userId;
    }

    public function getKeyHash(): string
    {
        return $this->keyHash;
    }

    public function setKeyHash(string $keyHash): void
    {
        $this->keyHash = $keyHash;
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
