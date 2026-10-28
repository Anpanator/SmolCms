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

    public function findAccessibleImage(int $id, AccessLevel $accessLevel, ?int $userId = null): ?ImageEntity
    {
        $image = $this->findImage($id);
        if ($image === null || !$this->canAccess($image, $accessLevel, $userId)) {
            return null;
        }
        return $image;
    }

    public function findImage(int $id): ?ImageEntity
    {
        return $this->imageEntityService->findOneById($id);
    }

    public function canAccess(ImageEntity $image, AccessLevel $accessLevel, ?int $userId = null): bool
    {
        if ($image->isDeleted()) {
            return $accessLevel->value >= AccessLevel::MOD->value;
        }

        if ($accessLevel->value >= AccessLevel::MOD->value
            || $image->getStatus() === ImageStatus::ACCEPTED) {
            return true;
        }

        $imageId = $image->getId();
        if ($image->getStatus() !== ImageStatus::PENDING_REVIEW || $imageId === null) {
            return false;
        }

        return $image->getUserId() === $userId
            || in_array('tagme', $this->tagService->getTagsForImage($imageId), true);
    }

    public function canAccessImageDetail(ImageEntity $image, AccessLevel $accessLevel, ?int $userId = null): bool
    {
        if ($image->isDeleted()) {
            return $accessLevel->value >= AccessLevel::MOD->value;
        }

        if ($accessLevel->value >= AccessLevel::MOD->value
            || $image->getStatus() === ImageStatus::ACCEPTED) {
            return true;
        }

        return $image->getStatus() === ImageStatus::PENDING_REVIEW
            && $image->getUserId() === $userId;
    }
}
