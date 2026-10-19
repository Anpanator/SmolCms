<?php
declare(strict_types=1);

namespace SmolCms\Service\Image;

use DateTime;
use SmolCms\Data\Constant\ImageStatus;
use SmolCms\Data\Image\ImageType;
use SmolCms\Data\Persistence\ImageEntity;
use SmolCms\Exception\InvalidStateException;
use SmolCms\Service\Core\Session\SessionService;
use SmolCms\Service\DB\ImageEntityService;

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
        $stored = $this->imageStorageService->storeOriginal($imageData, $imageType, $image);

        $entity = new ImageEntity(
            id: null,
            userId: $userData->id,
            uploadDate: new DateTime(),
            status: ImageStatus::PENDING,
            checksum: $stored->getChecksum(),
            originalExtension: $imageType->getFileExtension(),
            thumbFilename: null,
            sampleFilename: null,
            downloadFilename: null,
            width: $stored->getWidth(),
            height: $stored->getHeight(),
            format: $imageType->getFileExtension(),
            fileSize: $stored->getFileSize(),
        );
        $this->imageEntityService->saveAsNew($entity);

        return $entity;
    }
}
