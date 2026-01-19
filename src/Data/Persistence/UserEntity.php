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
        private ?int     $id,
        private string   $loginName,
        private string   $password,
        private string   $displayName,
        private string   $state,
        private DateTime $registerDate,
        private ?DateTime $lastLoginDate,
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

    public function getLoginName(): string
    {
        return $this->loginName;
    }

    public function setLoginName(string $loginName): void
    {
        $this->loginName = $loginName;
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function setPassword(string $password): void
    {
        $this->password = $password;
    }

    public function getDisplayName(): string
    {
        return $this->displayName;
    }

    public function setDisplayName(string $displayName): void
    {
        $this->displayName = $displayName;
    }

    public function getState(): string
    {
        return $this->state;
    }

    public function setState(string $state): void
    {
        $this->state = $state;
    }

    public function getRegisterDate(): DateTime
    {
        return $this->registerDate;
    }

    public function setRegisterDate(DateTime $registerDate): void
    {
        $this->registerDate = $registerDate;
    }

    public function getLastLoginDate(): ?DateTime
    {
        return $this->lastLoginDate;
    }

    public function setLastLoginDate(?DateTime $lastLoginDate): void
    {
        $this->lastLoginDate = $lastLoginDate;
    }
}