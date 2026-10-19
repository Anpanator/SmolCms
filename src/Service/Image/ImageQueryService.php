<?php
declare(strict_types=1);

namespace SmolCms\Service\Image;

use InvalidArgumentException;
use PDO;
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
    private const int IMAGES_PER_PAGE = 25;

    public function __construct(
        private ImageEntityService $imageEntityService,
        private UserService        $userService,
        private PDO                $pdo,
        private TagService         $tagService,
    )
    {
    }

    /** @return list<array{id: int, thumbSrc: ?string}> */
    public function getAcceptedImageSummaries(): array
    {
        return $this->getAcceptedImagePage(1)['images'];
    }

    /**
     * Accepted-image page and tag cloud. The subquery matches every requested tag,
     * while the cloud is calculated only over the images returned on this page.
     *
     * @param list<string> $tagNames
     * @return array{images: list<array{id: int, thumbSrc: ?string}>, page: int, perPage: int, total: int, tags: list<array{name: string, imageCount: int}>}
     */
    public function getAcceptedImagePage(int $page = 1, array $tagNames = []): array
    {
        if ($page < 1 || $page > intdiv(PHP_INT_MAX, self::IMAGES_PER_PAGE) + 1) {
            throw new InvalidArgumentException('Page must be a positive integer.');
        }

        $tagNames = self::deduplicateTagNames($tagNames);
        [$where, $parameters] = $this->buildAcceptedImageFilter($tagNames);

        $countStatement = $this->pdo->prepare('SELECT COUNT(*) FROM image i WHERE ' . $where);
        foreach ($parameters as $name => $value) {
            $countStatement->bindValue(':' . $name, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $countStatement->execute();
        $total = (int)$countStatement->fetchColumn();

        $statement = $this->pdo->prepare(
            'SELECT i.id, i.thumb_filename FROM image i WHERE ' . $where
            . ' ORDER BY i.upload_date DESC, i.id DESC LIMIT :limit OFFSET :offset'
        );
        foreach ($parameters as $name => $value) {
            $statement->bindValue(':' . $name, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $statement->bindValue(':limit', self::IMAGES_PER_PAGE, PDO::PARAM_INT);
        $statement->bindValue(':offset', ($page - 1) * self::IMAGES_PER_PAGE, PDO::PARAM_INT);
        $statement->execute();

        $images = [];
        while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
            $images[] = [
                'id' => (int)$row['id'],
                'thumbSrc' => $this->buildStaticThumbUrl(
                    $row['thumb_filename'] === null ? null : (string)$row['thumb_filename']
                ),
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
            'id' => $entity->getId(),
            'uploadDate' => $entity->getUploadDate()->format(self::UPLOAD_DATE_FORMAT),
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

    /** @return array{0: string, 1: array<string, string|int>} */
    private function buildAcceptedImageFilter(array $tagNames): array
    {
        $where = 'i.status = :status';
        $parameters = ['status' => ImageStatus::ACCEPTED->value];
        if ($tagNames === []) {
            return [$where, $parameters];
        }

        $placeholders = [];
        foreach ($tagNames as $index => $tagName) {
            $parameterName = 'tag_' . $index;
            $placeholders[] = ':' . $parameterName;
            $parameters[$parameterName] = $tagName;
        }
        $parameters['tag_count'] = count($tagNames);
        $where .= ' AND i.id IN ('
            . 'SELECT it.image_id FROM image_tag it '
            . 'INNER JOIN tag t ON t.id = it.tag_id '
            . 'WHERE t.name COLLATE NOCASE IN (' . implode(', ', $placeholders) . ') '
            . 'GROUP BY it.image_id HAVING COUNT(DISTINCT it.tag_id) = :tag_count'
            . ')';
        return [$where, $parameters];
    }

    /** @param list<string> $tagNames @return list<string> */
    private static function deduplicateTagNames(array $tagNames): array
    {
        $unique = [];
        foreach ($tagNames as $tagName) {
            if (!is_string($tagName)) {
                continue;
            }
            $tagName = trim($tagName);
            if ($tagName === '') {
                continue;
            }
            $unique[mb_strtolower($tagName, 'UTF-8')] ??= $tagName;
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
