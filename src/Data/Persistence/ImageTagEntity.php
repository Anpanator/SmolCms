<?php

declare(strict_types=1);

namespace SmolCms\Data\Persistence;

use SmolCms\Service\DB\Attribute\Entity;

/**
 * Image/tag association. Its primary key is composite (image_id, tag_id), so the
 * single-id Entity attribute is intentionally omitted.
 */
#[Entity(table: 'image_tag')]
class ImageTagEntity
{
    public function __construct(
        private ?int $imageId,
        private ?int $tagId,
    )
    {
    }

    public function getImageId(): ?int
    {
        return $this->imageId;
    }

    public function setImageId(?int $imageId): void
    {
        $this->imageId = $imageId;
    }

    public function getTagId(): ?int
    {
        return $this->tagId;
    }

    public function setTagId(?int $tagId): void
    {
        $this->tagId = $tagId;
    }
}
