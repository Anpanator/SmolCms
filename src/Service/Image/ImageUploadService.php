<?php
declare(strict_types=1);

namespace SmolCms\Service\Image;

use DateTime;
use SmolCms\Data\Constant\AuditActionType;
use SmolCms\Data\Constant\ContextKey;
use SmolCms\Data\Constant\ImageStatus;
use SmolCms\Data\Image\ImageType;
use SmolCms\Data\Persistence\ImageEntity;
use SmolCms\Exception\InvalidStateException;
use SmolCms\Service\Core\ContextService;
use SmolCms\Service\Core\Session\SessionService;
use SmolCms\Service\DB\AuditLogService;
use SmolCms\Service\DB\ImageEntityService;
use SmolCms\Service\Tag\TagService;
use Throwable;

/**
 * Orchestrates a single synchronous image upload: validate the image, store the
 * original bytes under IMAGE_STORAGE_DIR and create its pending database record.
 */
readonly class ImageUploadService
{
    public function __construct(
        private ImageProcessorFacade $imageProcessorFacade,
        private ImageStorageService  $imageStorageService,
        private ImageEntityService   $imageEntityService,
        private SessionService       $sessionService,
        private TagService           $tagService,
        private AuditLogService      $auditLogService,
        private ContextService       $contextService,
    )
    {
    }

    public function upload(string $imageData, ImageType $imageType): ImageEntity
    {
        $userData = $this->sessionService->getUserData();
        if ($userData === null) {
            throw new InvalidStateException('Cannot upload an image without an authenticated user.');
        }

        $image = $this->imageProcessorFacade->sanitizeImage($imageData, $imageType);
        $checksum = hash('sha256', $imageData);

        $entity = new ImageEntity(
            id: null,
            userId: $userData->id,
            uploadDate: new DateTime(),
            status: ImageStatus::PENDING,
            checksum: $checksum,
            originalExtension: $imageType->getFileExtension(),
            thumbFilename: null,
            sampleFilename: null,
            downloadFilename: null,
            width: imagesx($image),
            height: imagesy($image),
            format: $imageType->getFileExtension(),
            fileSize: strlen($imageData),
        );
        $this->imageEntityService->saveAsNew($entity);
        if ($entity->getId() === null) {
            throw new InvalidStateException('The uploaded image was saved without an ID.');
        }
        try {
            $this->imageStorageService->storeOriginal($imageData, $imageType, $image);
        } catch (Throwable $exception) {
            $this->imageEntityService->deleteById($entity->getId());
            throw $exception;
        }
        $this->tagService->attachTagToImage($entity->getId(), 'tagme');
        $context = 'image_id=' . $entity->getId();
        if ($this->contextService->getContext(ContextKey::AUTHENTICATED_VIA_API) === true) {
            $context .= ';via=api';
        }
        $this->auditLogService->record($userData->id, AuditActionType::IMAGE_UPLOADED, $context);

        return $entity;
    }
}
