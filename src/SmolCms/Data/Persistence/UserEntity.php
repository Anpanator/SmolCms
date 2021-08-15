<?php
declare(strict_types=1);

namespace SmolCms\Data\Persistence;


use DateTime;
use SmolCms\Service\DB\Attribute\Entity;

#[Entity(table: 'user')]
class UserEntity
{
    /**
     * UserEntity constructor.
     * @param int $id
     * @param string $loginName
     * @param string $displayName
     * @param string $state
     * @param DateTime $registerDate
     * @param DateTime|null $lastLoginDate
     */
    public function __construct(
        private int $id,
        private string $loginName,
        private string $displayName,
        private string $state,
        private DateTime $registerDate,
        private ?DateTime $lastLoginDate,
    )
    {
    }

    /**
     * @return int
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * @return string
     */
    public function getLoginName(): string
    {
        return $this->loginName;
    }

    /**
     * @return string
     */
    public function getDisplayName(): string
    {
        return $this->displayName;
    }

    /**
     * @return string
     */
    public function getState(): string
    {
        return $this->state;
    }

    /**
     * @return DateTime
     */
    public function getRegisterDate(): DateTime
    {
        return $this->registerDate;
    }

    /**
     * @return DateTime|null
     */
    public function getLastLoginDate(): ?DateTime
    {
        return $this->lastLoginDate;
    }
}