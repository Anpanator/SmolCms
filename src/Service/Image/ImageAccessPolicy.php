<?php

declare(strict_types=1);

namespace SmolCms\Service\Image;

use SmolCms\Data\Constant\AccessLevel;
use SmolCms\Data\Constant\ImageStatus;
use SmolCms\Data\Persistence\ImageEntity;
use SmolCms\Service\DB\ImageEntityService;
use SmolCms\Service\Tag\TagService;

final readonly class ImageAccessPolicy
{
    public function __construct(
        private ImageEntityService $imageEntityService,
        private TagService $tagService,
    )
    {
    }

    public function findAccessibleImage(int $id, AccessLevel $accessLevel): ?ImageEntity
    {
        $image = $this->imageEntityService->findOneById($id);
        if ($image === null || !$this->canAccess($image, $accessLevel)) {
            return null;
        }
        return $image;
    }

    public function canAccess(ImageEntity $image, AccessLevel $accessLevel): bool
    {
        if ($accessLevel->value >= AccessLevel::MOD->value
            || $image->getStatus() === ImageStatus::ACCEPTED) {
            return true;
        }

        $imageId = $image->getId();
        return $image->getStatus() === ImageStatus::PENDING_REVIEW
            && $imageId !== null
            && in_array('tagme', $this->tagService->getTagsForImage($imageId), true);
    }
}
