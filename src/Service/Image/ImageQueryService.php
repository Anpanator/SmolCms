<?php
declare(strict_types=1);

namespace SmolCms\Service\Image;

use InvalidArgumentException;
use SmolCms\Data\Constant\ImageStatus;
use SmolCms\Data\Constant\RouteEnum;
use SmolCms\Data\Persistence\ImageEntity;
use SmolCms\Service\DB\ImageEntityService;
use SmolCms\Service\DB\UserService;
use SmolCms\Service\Tag\TagService;

/**
 * Read-only queries for image display: builds the JSON-ready view data for the
 * home grid, the image detail view and the client-facing image sources.
 */
readonly class ImageQueryService
{
    private const UPLOAD_DATE_FORMAT = 'Y-m-d H:i:s';
    private const int IMAGES_PER_PAGE = 24;

    public function __construct(
        private ImageEntityService $imageEntityService,
        private UserService        $userService,
        private TagService         $tagService,
    )
    {
    }

    /**
     * Visible-image page and tag cloud. The subquery matches every requested tag,
     * while the cloud is calculated only over the images returned on this page.
     *
     * @param list<string> $tagNames
     * @return array{images: list<array{id: int, thumbSrc: ?string, status: string}>, page: int, perPage: int, total: int, tags: list<array{name: string, imageCount: int}>}
     */
    public function getImagePage(
        int $page = 1,
        array $tagNames = [],
        ?int $viewerId = null,
        bool $includeAllPending = false,
    ): array
    {
        if ($page < 1 || $page > intdiv(PHP_INT_MAX, self::IMAGES_PER_PAGE) + 1) {
            throw new InvalidArgumentException('Page must be a positive integer.');
        }

        $tagNames = self::deduplicateTagNames($tagNames);
        $offset = ($page - 1) * self::IMAGES_PER_PAGE;

        $total = $this->imageEntityService->countVisible($tagNames, $viewerId, $includeAllPending);
        $images = [];
        foreach ($this->imageEntityService->findVisiblePage(
            self::IMAGES_PER_PAGE,
            $offset,
            $tagNames,
            $viewerId,
            $includeAllPending,
        ) as $entity) {
            $images[] = [
                'id' => $entity->getId(),
                'thumbSrc' => $this->getThumbSrc($entity),
                'status' => $entity->getStatus()->value,
            ];
        }
        $imageIds = array_column($images, 'id');

        return [
            'images' => $images,
            'page' => $page,
            'perPage' => self::IMAGES_PER_PAGE,
            'total' => $total,
            'tags' => $this->tagService->getTopTagsForImages($imageIds, self::IMAGES_PER_PAGE),
        ];
    }

    /**
     * Recent thumbnail-ready uploads belonging to a user.
     *
     * @return list<array{id: int, thumbSrc: ?string, status: string, uploadDate: string}>
     */
    public function getRecentUploadsForUser(int $userId, int $limit, bool $includePendingReview = true): array
    {
        $uploads = [];
        foreach ($this->imageEntityService->findRecentByUser(
            $userId,
            $limit,
            $includePendingReview
                ? [ImageStatus::PENDING_REVIEW, ImageStatus::ACCEPTED]
                : [ImageStatus::ACCEPTED],
        ) as $entity) {
            $uploads[] = [
                'id' => $entity->getId(),
                'thumbSrc' => $this->getThumbSrc($entity),
                'status' => $entity->getStatus()->value,
                'uploadDate' => $entity->getUploadDate()->format(self::UPLOAD_DATE_FORMAT),
            ];
        }
        return $uploads;
    }

    /**
     * Nearest detail-visible neighbor image id (next or previous by id), or
     * null when no accessible image exists in that direction. Inaccessible
     * images (e.g. other users' unapproved uploads) are skipped.
     */
    public function getNeighborId(int $id, bool $next, ?int $viewerId, bool $canAccessAll): ?int
    {
        return $next
            ? $this->imageEntityService->findNextAccessibleId($id, $viewerId, $canAccessAll)
            : $this->imageEntityService->findPreviousAccessibleId($id, $viewerId, $canAccessAll);
    }

    /**
     * Full detail data for a single image, or null for an unknown id.
     *
     * @return array<string, int|string|null|list<string>>|null
     */
    public function getImageDetail(int $id): ?array
    {
        $entity = $this->imageEntityService->findOneById($id);
        if ($entity === null) {
            return null;
        }
        $uploader = $this->userService->findOneById($entity->getUserId());
        return [
            'status' => $entity->getStatus()->value,
            'id' => $entity->getId(),
            'uploadDate' => $entity->getUploadDate()->format(self::UPLOAD_DATE_FORMAT),
            'uploaderId' => $uploader?->getId(),
            'uploader' => $uploader?->getDisplayName(),
            'width' => $entity->getWidth(),
            'height' => $entity->getHeight(),
            'format' => $entity->getFormat(),
            'fileSize' => $entity->getFileSize(),
            'sampleSrc' => $this->getSampleSrc($entity),
            'downloadSrc' => $this->getDownloadSrc($entity),
            'tags' => $this->tagService->getTagsForImage($id),
        ];
    }

    /** @param list<string> $tagNames @return list<string> */
    private static function deduplicateTagNames(array $tagNames): array
    {
        $unique = [];
        foreach ($tagNames as $tagName) {
            if (!is_string($tagName)) {
                continue;
            }
            $tagName = TagService::normalizeTagName($tagName);
            if ($tagName === '') {
                continue;
            }
            $unique[$tagName] ??= $tagName;
        }
        return array_values($unique);
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
