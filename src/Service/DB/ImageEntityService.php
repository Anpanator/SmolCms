<?php
declare(strict_types=1);

namespace SmolCms\Service\DB;

use PDO;
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

    public function hasPendingOriginal(string $checksum, string $originalExtension): bool
    {
        $qc = new QueryCriteria();
        $qc->select(ImageEntity::class)
            ->andWhere('checksum = :checksum')
            ->andWhere('original_extension = :originalExtension')
            ->andWhere('status = :status')
            ->withParameters([
                'checksum' => $checksum,
                'originalExtension' => $originalExtension,
                'status' => ImageStatus::PENDING->value,
            ])
            ->maxResults(1);
        return ($this->execute($qc) ?? []) !== [];
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
