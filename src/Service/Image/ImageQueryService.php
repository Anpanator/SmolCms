<?php
declare(strict_types=1);

namespace SmolCms\Service\Image;

use SmolCms\Data\Constant\ImageStatus;
use SmolCms\Data\Constant\RouteEnum;
use SmolCms\Data\Persistence\ImageEntity;
use SmolCms\Service\DB\ImageEntityService;
use SmolCms\Service\DB\UserService;

/**
 * Read-only queries for image display: builds the JSON-ready view data for the
 * home grid, the image detail view and the client-facing image sources.
 */
readonly class ImageQueryService
{
    private const UPLOAD_DATE_FORMAT = 'Y-m-d H:i:s';

    public function __construct(
        private ImageEntityService $imageEntityService,
        private UserService        $userService,
    )
    {
    }

    /**
     * Summaries of all ACCEPTED images for the public home grid, newest first.
     *
     * @return array<array{id: int, thumbSrc: ?string}>
     */
    public function getAcceptedImageSummaries(): array
    {
        $entities = $this->imageEntityService->findByStatus(ImageStatus::ACCEPTED);
        // findByStatus returns upload_date ASC (oldest first); the grid wants newest first.
        $entities = array_reverse($entities);
        return array_map(
            fn(ImageEntity $entity) => [
                'id' => $entity->getId(),
                'thumbSrc' => $this->getThumbSrc($entity),
            ],
            $entities
        );
    }

    /**
     * Full detail data for a single image, or null for an unknown id.
     *
     * @return array<string, int|string|null>|null
     */
    public function getImageDetail(int $id): ?array
    {
        $entity = $this->imageEntityService->findOneById($id);
        if ($entity === null) {
            return null;
        }
        $uploader = $this->userService->findOneById($entity->getUserId());
        return [
            'id' => $entity->getId(),
            'uploadDate' => $entity->getUploadDate()->format(self::UPLOAD_DATE_FORMAT),
            'uploader' => $uploader?->getDisplayName(),
            'width' => $entity->getWidth(),
            'height' => $entity->getHeight(),
            'format' => $entity->getFormat(),
            'fileSize' => $entity->getFileSize(),
            'sampleSrc' => $this->getSampleSrc($entity),
            'downloadSrc' => $this->getDownloadSrc($entity),
        ];
    }

    /**
     * Public URL of an image's thumbnail (a static file served by nginx), or null
     * while the image has not been processed yet.
     */
    public function getThumbSrc(ImageEntity $entity): ?string
    {
        return $this->buildStaticThumbUrl($entity->getThumbFilename());
    }

    /**
     * URL of the protected viewing image endpoint, or null while the image has not
     * been processed yet. The endpoint itself enforces authentication and status.
     */
    public function getSampleSrc(ImageEntity $entity): ?string
    {
        if ($entity->getSampleFilename() === null) {
            return null;
        }
        return RouteEnum::API_IMAGE_SAMPLE->value . '?name=' . rawurlencode($entity->getSampleFilename());
    }

    /**
     * URL of the protected download endpoint, or null while the image has not been
     * processed yet. The endpoint itself enforces authentication and status.
     */
    public function getDownloadSrc(ImageEntity $entity): ?string
    {
        if ($entity->getDownloadFilename() === null) {
            return null;
        }
        return RouteEnum::API_IMAGE_DOWNLOAD->value . '?name=' . rawurlencode($entity->getDownloadFilename());
    }

    private function buildStaticThumbUrl(?string $thumbFilename): ?string
    {
        if ($thumbFilename === null) {
            return null;
        }
        return '/public/img/thumb/' . ImagePathLayout::relativePathForFileName($thumbFilename);
    }
}
