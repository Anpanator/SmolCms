<?php

declare(strict_types=1);

namespace SmolCms\Data\Persistence;

use DateTime;
use SmolCms\Data\Constant\AuditActionType;
use SmolCms\Service\DB\Attribute\Entity;
use SmolCms\Service\DB\Attribute\Id;

#[Entity(table: 'audit_log')]
class AuditLogEntity
{
    public function __construct(
        #[Id]
        private ?int $id,
        private int $userId,
        private AuditActionType $actionType,
        private string $context,
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

    public function getActionType(): AuditActionType
    {
        return $this->actionType;
    }

    public function setActionType(AuditActionType $actionType): void
    {
        $this->actionType = $actionType;
    }

    public function getContext(): string
    {
        return $this->context;
    }

    public function setContext(string $context): void
    {
        $this->context = $context;
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
