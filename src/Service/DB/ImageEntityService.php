<?php
declare(strict_types=1);

namespace SmolCms\Service\DB;

use PDO;
use SmolCms\Data\Constant\ImageFileVariant;
use SmolCms\Data\Constant\ImageStatus;
use SmolCms\Data\Persistence\ImageEntity;
use SmolCms\Service\Core\CaseConverter;
use SmolCms\Service\Tag\TagService;

readonly class ImageEntityService extends EntityService
{
    public function __construct(
        PDO                      $pdo,
        CaseConverter            $caseConverter,
        QueryBuilder             $queryBuilder,
        EntityAttributeProcessor $entityAttributeProcessor,
        private TagService       $tagService,
    )
    {
        parent::__construct($pdo, $caseConverter, $queryBuilder, $entityAttributeProcessor);
    }

    public function findOneById(int $id): ?ImageEntity
    {
        $qc = new QueryCriteria();
        $qc->select(ImageEntity::class)
            ->andWhere('id = :id')
            ->withParameters(['id' => $id])
            ->maxResults(1);
        $result = $this->execute($qc);
        return $result[0] ?? null;
    }

    public function findOneBySampleFilename(string $sampleFilename): ?ImageEntity
    {
        return $this->findOneByColumn('sample_filename', $sampleFilename);
    }

    public function findOneByDownloadFilename(string $downloadFilename): ?ImageEntity
    {
        return $this->findOneByColumn('download_filename', $downloadFilename);
    }

    /**
     * @param ImageStatus $status
     * @param int|null $limit
     * @return array<ImageEntity>
     */
    public function findByStatus(ImageStatus $status, ?int $limit = null): array
    {
        $qc = new QueryCriteria();
        $qc->select(ImageEntity::class)
            ->andWhere('status = :status')
            ->withParameters(['status' => $status->value])
            ->orderBy('upload_date');
        if ($limit !== null) {
            $qc->maxResults($limit);
        }
        $result = $this->execute($qc);
        return $result ?? [];
    }

    /**
     * Page of accepted images, newest first. Optionally restricted to images that
     * carry every requested tag.
     *
     * @param list<string> $tagNames
     * @return array<ImageEntity>
     */
    public function findAcceptedPage(int $limit, int $offset, array $tagNames = []): array
    {
        $qc = new QueryCriteria();
        $qc->select(ImageEntity::class)
            ->orderBy('upload_date', QueryCriteria::ORDER_DESC)
            ->orderBy('id', QueryCriteria::ORDER_DESC)
            ->maxResults($limit)
            ->skipResults($offset);
        self::applyAcceptedFilter($qc, $tagNames);
        return $this->execute($qc) ?? [];
    }

    /**
     * Page of accepted images and pending-review images visible to this viewer,
     * newest first. MOD+ viewers can include all pending-review images; other
     * viewers can include only their own.
     *
     * @param list<string> $tagNames
     * @return array<ImageEntity>
     */
    public function findVisiblePage(
        int $limit,
        int $offset,
        array $tagNames = [],
        ?int $viewerId = null,
        bool $includeAllPending = false,
    ): array {
        $qc = new QueryCriteria();
        $qc->select(ImageEntity::class)
            ->orderBy('upload_date', QueryCriteria::ORDER_DESC)
            ->orderBy('id', QueryCriteria::ORDER_DESC)
            ->maxResults($limit)
            ->skipResults($offset);
        self::applyVisibleFilter($qc, $tagNames, $viewerId, $includeAllPending);
        return $this->execute($qc) ?? [];
    }

    /**
     * Recent processed images belonging to one user, newest first.
     *
     * @param list<ImageStatus> $statuses
     * @return list<ImageEntity>
     */
    public function findRecentByUser(int $userId, int $limit, array $statuses): array
    {
        if ($limit < 1 || $statuses === []) {
            return [];
        }

        $parameters = ['user_id' => $userId];
        $statusPlaceholders = [];
        foreach (array_values($statuses) as $index => $status) {
            $parameterName = 'status_' . $index;
            $statusPlaceholders[] = ':' . $parameterName;
            $parameters[$parameterName] = $status->value;
        }

        $qc = new QueryCriteria();
        $qc->select(ImageEntity::class)
            ->andWhere('user_id = :user_id')
            ->andWhere('thumb_filename IS NOT NULL')
            ->andWhere('status IN (' . implode(', ', $statusPlaceholders) . ')')
            ->withParameters($parameters)
            ->orderBy('upload_date', QueryCriteria::ORDER_DESC)
            ->orderBy('id', QueryCriteria::ORDER_DESC)
            ->maxResults($limit);

        return $this->execute($qc) ?? [];
    }

    /**
     * Total number of accepted images, optionally restricted to images that carry
     * every requested tag.
     *
     * @param list<string> $tagNames
     */
    public function countAccepted(array $tagNames = []): int
    {
        $qc = new QueryCriteria();
        $qc->select(ImageEntity::class);
        self::applyAcceptedFilter($qc, $tagNames);
        return $this->countResults($qc);
    }

    /**
     * Total accepted and visible pending-review images, optionally restricted to
     * images that carry every requested tag.
     *
     * @param list<string> $tagNames
     */
    public function countVisible(
        array $tagNames = [],
        ?int $viewerId = null,
        bool $includeAllPending = false,
    ): int {
        $qc = new QueryCriteria();
        $qc->select(ImageEntity::class);
        self::applyVisibleFilter($qc, $tagNames, $viewerId, $includeAllPending);
        return $this->countResults($qc);
    }

    public function findRandomTagmeImage(): ?ImageEntity
    {
        $driver = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        $tagNameCondition = $driver === 'sqlite'
            ? 't.name COLLATE NOCASE = :tag_name'
            : 't.name = :tag_name';
        $randomOrder = $driver === 'mysql' ? 'RAND()' : 'RANDOM()';

        $criteria = new QueryCriteria();
        $criteria->select(ImageEntity::class)
            ->andWhere('(status = :accepted_status OR status = :pending_review_status)')
            ->andWhere(
                'id IN (SELECT it.image_id FROM image_tag it '
                . 'INNER JOIN tag t ON t.id = it.tag_id '
                . 'WHERE ' . $tagNameCondition . ')'
            )
            ->withParameters([
                'accepted_status' => ImageStatus::ACCEPTED->value,
                'pending_review_status' => ImageStatus::PENDING_REVIEW->value,
                'tag_name' => 'tagme',
            ])
            ->orderBy($randomOrder)
            ->maxResults(1);

        return ($this->execute($criteria) ?? [])[0] ?? null;
    }

    /**
     * Finds images whose variants were saved by the processing worker.
     * These rows are also used to retry original-file cleanup after a worker interruption.
     *
     * @return array<ImageEntity>
     */
    public function findProcessedImages(): array
    {
        $qc = new QueryCriteria();
        $qc->select(ImageEntity::class)
            ->andWhere('status != :pendingStatus')
            ->andWhere('thumb_filename IS NOT NULL')
            ->andWhere('sample_filename IS NOT NULL')
            ->andWhere('download_filename IS NOT NULL')
            ->withParameters(['pendingStatus' => ImageStatus::PENDING->value])
            ->orderBy('upload_date');
        $result = $this->execute($qc);
        return $result ?? [];
    }

    public function hasOtherImageReference(ImageEntity $image, ImageFileVariant $variant): bool
    {
        $imageId = $image->getId();
        if ($imageId === null) {
            return false;
        }

        $criteria = new QueryCriteria();
        $criteria->select(ImageEntity::class)
            ->andWhere('id != :image_id')
            ->maxResults(1);
        $parameters = ['image_id' => $imageId];

        switch ($variant) {
            case ImageFileVariant::ORIGINAL:
                $criteria->andWhere('checksum = :checksum')
                    ->andWhere('original_extension = :original_extension');
                $parameters['checksum'] = $image->getChecksum();
                $parameters['original_extension'] = $image->getOriginalExtension();
                break;
            case ImageFileVariant::THUMB:
                $filename = $image->getThumbFilename();
                if ($filename === null) {
                    return false;
                }
                $criteria->andWhere('thumb_filename = :filename');
                $parameters['filename'] = $filename;
                break;
            case ImageFileVariant::SAMPLE:
                $filename = $image->getSampleFilename();
                if ($filename === null) {
                    return false;
                }
                $criteria->andWhere('sample_filename = :filename');
                $parameters['filename'] = $filename;
                break;
            case ImageFileVariant::DOWNLOAD:
                $filename = $image->getDownloadFilename();
                if ($filename === null) {
                    return false;
                }
                $criteria->andWhere('download_filename = :filename');
                $parameters['filename'] = $filename;
                break;
        }

        $criteria->withParameters($parameters);
        return ($this->execute($criteria) ?? []) !== [];
    }

    public function deleteById(int $id): void
    {
        $this->withinTransaction(function () use ($id): void {
            $this->tagService->detachAllFromImage($id);

            $qc = new QueryCriteria();
            $qc->delete(ImageEntity::class)
                ->andWhere('id = :id')
                ->withParameters(['id' => $id]);
            $this->execute($qc);
        });
    }

    private static function applyAcceptedFilter(QueryCriteria $qc, array $tagNames): void
    {
        $parameters = ['status' => ImageStatus::ACCEPTED->value];
        $qc->andWhere('status = :status');
        if ($tagNames === []) {
            $qc->withParameters($parameters);
            return;
        }

        $placeholders = [];
        foreach (array_values($tagNames) as $index => $tagName) {
            $parameterName = 'tag_' . $index;
            $placeholders[] = ':' . $parameterName;
            $parameters[$parameterName] = $tagName;
        }
        $parameters['tag_count'] = count($tagNames);
        $qc->andWhere('id IN ('
            . 'SELECT it.image_id FROM image_tag it '
            . 'INNER JOIN tag t ON t.id = it.tag_id '
            . 'WHERE t.name COLLATE NOCASE IN (' . implode(', ', $placeholders) . ') '
            . 'GROUP BY it.image_id HAVING COUNT(DISTINCT it.tag_id) = :tag_count'
            . ')')
            ->withParameters($parameters);
    }

    /** @param list<string> $tagNames */
    private static function applyVisibleFilter(
        QueryCriteria $qc,
        array $tagNames,
        ?int $viewerId,
        bool $includeAllPending,
    ): void {
        $parameters = ['accepted_status' => ImageStatus::ACCEPTED->value];
        if ($includeAllPending) {
            $pendingCondition = 'status = :pending_review_status';
        } elseif ($viewerId !== null) {
            $pendingCondition = '(status = :pending_review_status AND user_id = :viewer_id)';
            $parameters['viewer_id'] = $viewerId;
        } else {
            $pendingCondition = null;
        }

        if ($pendingCondition === null) {
            $qc->andWhere('status = :accepted_status');
        } else {
            $parameters['pending_review_status'] = ImageStatus::PENDING_REVIEW->value;
            $qc->andWhere('(status = :accepted_status OR ' . $pendingCondition . ')');
        }

        if ($tagNames !== []) {
            $placeholders = [];
            foreach (array_values($tagNames) as $index => $tagName) {
                $parameterName = 'tag_' . $index;
                $placeholders[] = ':' . $parameterName;
                $parameters[$parameterName] = $tagName;
            }
            $parameters['tag_count'] = count($tagNames);
            $qc->andWhere('id IN ('
                . 'SELECT it.image_id FROM image_tag it '
                . 'INNER JOIN tag t ON t.id = it.tag_id '
                . 'WHERE t.name COLLATE NOCASE IN (' . implode(', ', $placeholders) . ') '
                . 'GROUP BY it.image_id HAVING COUNT(DISTINCT it.tag_id) = :tag_count'
                . ')');
        }

        $qc->withParameters($parameters);
    }

    private function findOneByColumn(string $column, string $value): ?ImageEntity
    {
        $qc = new QueryCriteria();
        $qc->select(ImageEntity::class)
            ->andWhere("$column = :$column")
            ->withParameters([$column => $value])
            ->maxResults(1);
        $result = $this->execute($qc);
        return $result[0] ?? null;
    }
}
