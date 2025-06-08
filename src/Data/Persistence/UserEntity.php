<?php
declare(strict_types=1);

namespace SmolCms\Data\Persistence;


use DateTime;
use SmolCms\Service\DB\Attribute\Entity;
use SmolCms\Service\DB\Attribute\Id;

#[Entity(table: 'user')]
class UserEntity
{
    public function __construct(
        #[Id]
        private ?int   $id,
        private string $loginName,
        private string $password,
        private string $displayName,
        private string $state,
        private DateTime $registerDate,
        private ?DateTime $lastLoginDate,
    )
    {
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getLoginName(): string
    {
        return $this->loginName;
    }

    public function getDisplayName(): string
    {
        return $this->displayName;
    }

    public function getState(): string
    {
        return $this->state;
    }

    public function getRegisterDate(): DateTime
    {
        return $this->registerDate;
    }

    public function getLastLoginDate(): ?DateTime
    {
        return $this->lastLoginDate;
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function setPassword(string $password): void
    {
        $this->password = $password;
    }
}