<?php
declare(strict_types=1);

namespace SmolCms\Service\Image;

use SmolCms\Data\Constant\ImageFileVariant;
use SmolCms\Data\Constant\ImageStatus;
use SmolCms\Data\Constant\LogLevel;
use SmolCms\Data\Persistence\ImageEntity;
use SmolCms\Exception\FileAccessException;
use SmolCms\Service\Core\LoggingService;
use SmolCms\Service\DB\ImageEntityService;
use SmolCms\Service\DB\UserService;
use SmolCms\Service\File\FileSystemAccessService;

/**
 * Review-side image moderation: queue listing, approval and rejection.
 *
 * approve() is idempotent: approving an image that is not PENDING_REVIEW anymore
 * (e.g. already ACCEPTED) is a no-op success. reject() deletes the database row
 * and all related image files; file deletion is best-effort — missing or
 * undeletable files never abort the row deletion and are logged instead.
 */
readonly class ImageReviewService
{
    private const UPLOAD_DATE_FORMAT = 'Y-m-d H:i:s';

    public function __construct(
        private ImageEntityService      $imageEntityService,
        private ImageQueryService       $imageQueryService,
        private UserService             $userService,
        private LoggingService          $loggingService,
        private FileSystemAccessService $fileSystemAccessService,
    )
    {
    }

    /**
     * Review queue entries for all PENDING_REVIEW images, oldest first.
     *
     * @return array<array<string, int|string|null>>
     */
    public function getReviewQueue(): array
    {
        $entities = $this->imageEntityService->findByStatus(ImageStatus::PENDING_REVIEW);
        return array_map(
            fn(ImageEntity $entity) => [
                'id' => $entity->getId(),
                'thumbSrc' => $this->imageQueryService->getThumbSrc($entity),
                'sampleSrc' => $this->imageQueryService->getSampleSrc($entity),
                'uploadDate' => $entity->getUploadDate()->format(self::UPLOAD_DATE_FORMAT),
                'uploader' => $this->userService->findOneById($entity->getUserId())?->getDisplayName(),
            ],
            $entities
        );
    }

    /**
     * Approves an image. Returns false only for an unknown id; for an image that is
     * no longer PENDING_REVIEW the call is an idempotent no-op and returns true.
     */
    public function approve(int $id): bool
    {
        $entity = $this->imageEntityService->findOneById($id);
        if ($entity === null) {
            return false;
        }
        if ($entity->getStatus() !== ImageStatus::PENDING_REVIEW) {
            return true;
        }
        $entity->setStatus(ImageStatus::ACCEPTED);
        $this->imageEntityService->update($entity);
        return true;
    }

    /**
     * Rejects an image: deletes the database row and all related image files
     * (original, thumb, sample, download). Returns false only for an unknown id.
     */
    public function reject(int $id): bool
    {
        $entity = $this->imageEntityService->findOneById($id);
        if ($entity === null) {
            return false;
        }
        $this->deleteImageFiles($entity);
        $this->imageEntityService->deleteById($id);
        return true;
    }

    private function deleteImageFiles(ImageEntity $entity): void
    {
        $files = [
            [
                'path' => IMAGE_STORAGE_DIR . '/' . ImagePathLayout::relativePath(
                    $entity->getChecksum(),
                    $entity->getOriginalExtension()
                ),
                'variant' => ImageFileVariant::ORIGINAL,
            ],
        ];
        $variantFileNames = [
            IMAGE_THUMB_DIR => [$entity->getThumbFilename(), ImageFileVariant::THUMB],
            IMAGE_SAMPLE_DIR => [$entity->getSampleFilename(), ImageFileVariant::SAMPLE],
            IMAGE_DOWNLOAD_DIR => [$entity->getDownloadFilename(), ImageFileVariant::DOWNLOAD],
        ];
        foreach ($variantFileNames as $baseDir => [$fileName, $variant]) {
            if ($fileName !== null) {
                $files[] = [
                    'path' => $baseDir . '/' . ImagePathLayout::relativePathForFileName($fileName),
                    'variant' => $variant,
                ];
            }
        }

        foreach ($files as ['path' => $path, 'variant' => $variant]) {
            if ($this->imageEntityService->hasOtherImageReference($entity, $variant)) {
                continue;
            }
            if (!is_file($path)) {
                continue;
            }
            try {
                $this->fileSystemAccessService->deleteFile($path);
            } catch (FileAccessException) {
                $this->loggingService->log(
                    LogLevel::WARNING,
                    sprintf('Could not delete image file "%s" of image %d.', $path, $entity->getId())
                );
            }
        }
    }
}
