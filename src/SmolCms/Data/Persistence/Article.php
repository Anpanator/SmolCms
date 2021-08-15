<?php
declare(strict_types=1);

namespace SmolCms\Data\Persistence;


use DateTime;
use SmolCms\Service\DB\Attribute\Entity;

#[Entity(table: 'article')]
class Article
{
    /**
     * ArticleEntity constructor.
     * @param int $id
     * @param string $slug
     * @param string $title
     * @param string $state
     * @param string $content
     * @param DateTime $created
     * @param DateTime $updated
     */
    public function __construct(
        private int $id,
        private string $slug,
        private string $title,
        private string $state,
        private string $content,
        private DateTime $created,
        private DateTime $updated,
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
    public function getSlug(): string
    {
        return $this->slug;
    }

    /**
     * @return string
     */
    public function getTitle(): string
    {
        return $this->title;
    }

    /**
     * @return string
     */
    public function getState(): string
    {
        return $this->state;
    }

    /**
     * @return string
     */
    public function getContent(): string
    {
        return $this->content;
    }

    /**
     * @return DateTime
     */
    public function getCreated(): DateTime
    {
        return $this->created;
    }

    /**
     * @return DateTime
     */
    public function getUpdated(): DateTime
    {
        return $this->updated;
    }
}