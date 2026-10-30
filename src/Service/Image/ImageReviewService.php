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
 * Image moderation: review queue listing, approval and rejection, plus the
 * delete queue (soft-deleted images) with restore and permanent deletion.
 *
 * approve() is idempotent: approving an image that is not PENDING_REVIEW anymore
 * (e.g. already ACCEPTED) is a no-op success. reject() deletes the database row
 * and all related image files; file deletion is best-effort — missing or
 * undeletable files never abort the row deletion and are logged instead.
 *
 * queueForDeletion() hides an image from every regular view while keeping its
 * row and files so it can be restored; delete() removes it for real.
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
     * Review queue entries for all visible PENDING_REVIEW images, oldest first.
     *
     * @return list<array{id: int, thumbSrc: ?string, sampleSrc: ?string, uploadDate: string, uploaderId: ?int, uploader: ?string}>
     */
    public function getReviewQueue(): array
    {
        return $this->mapQueueEntries($this->imageEntityService->findReviewQueue());
    }

    /**
     * Delete queue entries for all soft-deleted images, most recently queued first.
     *
     * @return list<array{id: int, thumbSrc: ?string, sampleSrc: ?string, uploadDate: string, uploaderId: ?int, uploader: ?string}>
     */
    public function getDeletionQueue(): array
    {
        return $this->mapQueueEntries($this->imageEntityService->findDeletionQueue());
    }

    /**
     * Moves an image to the delete queue without deleting its row or files.
     * Returns false only for an unknown id; queueing an already queued image is
     * an idempotent no-op success.
     */
    public function queueForDeletion(int $id): bool
    {
        $entity = $this->imageEntityService->findOneById($id);
        if ($entity === null) {
            return false;
        }
        if ($entity->isDeleted()) {
            return true;
        }
        $entity->queueForDeletion();
        $this->imageEntityService->update($entity);
        return true;
    }

    /**
     * Restores an image from the delete queue, making it visible again
     * according to its lifecycle status. Returns false only for an unknown id;
     * restoring an image that is not queued is an idempotent no-op success.
     */
    public function restore(int $id): bool
    {
        $entity = $this->imageEntityService->findOneById($id);
        if ($entity === null) {
            return false;
        }
        if (!$entity->isDeleted()) {
            return true;
        }
        $entity->restoreFromDeletionQueue();
        $this->imageEntityService->update($entity);
        return true;
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
        return $this->delete($id);
    }

    /**
     * Permanently deletes an image that is waiting in the delete queue: deletes
     * the database row and all related image files (original, thumb, sample,
     * download). Returns false for an unknown id and for an image that is not
     * queued for deletion.
     */
    public function deleteQueued(int $id): bool
    {
        $entity = $this->imageEntityService->findOneById($id);
        if ($entity === null || !$entity->isDeleted()) {
            return false;
        }
        $this->deleteImageFiles($entity);
        $this->imageEntityService->deleteById($id);
        return true;
    }

    /**
     * Permanently deletes every image currently waiting in the delete queue.
     *
     * @return list<int> IDs of images deleted from the queue
     */
    public function deleteAllQueued(): array
    {
        $deletedIds = [];
        foreach ($this->imageEntityService->findDeletionQueue() as $entity) {
            $id = $entity->getId();
            if ($id !== null && $this->deleteQueued($id)) {
                $deletedIds[] = $id;
            }
        }
        return $deletedIds;
    }

    /**
     * Deletes an image of any status: deletes the database row and all related
     * image files (original, thumb, sample, download). Returns false only for
     * an unknown id.
     */
    public function delete(int $id): bool
    {
        $entity = $this->imageEntityService->findOneById($id);
        if ($entity === null) {
            return false;
        }
        $this->deleteImageFiles($entity);
        $this->imageEntityService->deleteById($id);
        return true;
    }

    /**
     * @param array<ImageEntity> $entities
     * @return list<array{id: int, thumbSrc: ?string, sampleSrc: ?string, uploadDate: string, uploaderId: ?int, uploader: ?string}>
     */
    private function mapQueueEntries(array $entities): array
    {
        return array_map(
            function (ImageEntity $entity): array {
                $uploader = $this->userService->findOneById($entity->getUserId());
                return [
                    'id' => $entity->getId(),
                    'thumbSrc' => $this->imageQueryService->getThumbSrc($entity),
                    'sampleSrc' => $this->imageQueryService->getSampleSrc($entity),
                    'uploadDate' => $entity->getUploadDate()->format(self::UPLOAD_DATE_FORMAT),
                    'uploaderId' => $uploader?->getId(),
                    'uploader' => $uploader?->getDisplayName(),
                ];
            },
            $entities
        );
    }

    private function deleteImageFiles(ImageEntity $entity): void
    {
        $files = [
            [
                'path' => IMAGE_STORAGE_DIR . '/' . ImagePathLayout::originalRelativePath(
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
            try {
                if (!$this->fileSystemAccessService->fileExists($path)) {
                    continue;
                }
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
