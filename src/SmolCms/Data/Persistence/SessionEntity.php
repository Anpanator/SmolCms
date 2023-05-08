<?php
declare(strict_types=1);

namespace SmolCms\Data\Persistence;

use DateTime;
use SmolCms\Service\DB\Attribute\Entity;
use SmolCms\Service\Validation\Attribute\ValidateNotNull;
use SmolCms\Service\Validation\Attribute\ValidateStringSizeBytes;

#[Entity('session')]
class SessionEntity
{
    public function __construct(
        private ?int                                 $id,
        #[ValidateStringSizeBytes(max: 64)]
        #[ValidateNotNull]
        private string                               $sessionId,
        private ?DateTime                            $created,
        #[ValidateStringSizeBytes(max: 2 ** 24 - 1)] // 16 MiB
        #[ValidateNotNull]
        private string $data
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

    public function getSessionId(): string
    {
        return $this->sessionId;
    }

    public function setSessionId(string $sessionId): void
    {
        $this->sessionId = $sessionId;
    }

    public function getCreated(): ?DateTime
    {
        return $this->created;
    }

    public function setCreated(?DateTime $created): void
    {
        $this->created = $created;
    }

    public function getData(): string
    {
        return $this->data;
    }

    public function setData(string $data): void
    {
        $this->data = $data;
    }
}