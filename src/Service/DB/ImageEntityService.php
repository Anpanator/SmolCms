<?php
declare(strict_types=1);

namespace SmolCms\Service\DB;

use Throwable;
use SmolCms\Data\Constant\ImageStatus;
use SmolCms\Data\Persistence\ImageEntity;

readonly class ImageEntityService extends EntityService
{
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
        $ownsTransaction = !$this->pdo->inTransaction();
        if ($ownsTransaction) {
            $this->pdo->beginTransaction();
        }

        try {
            $usageStatement = $this->pdo->prepare(
                'UPDATE tag SET usage_count = CASE WHEN usage_count > 0 THEN usage_count - 1 ELSE 0 END '
                . 'WHERE id IN (SELECT tag_id FROM image_tag WHERE image_id = :image_id)'
            );
            $usageStatement->execute(['image_id' => $id]);

            $tagStatement = $this->pdo->prepare('DELETE FROM image_tag WHERE image_id = :image_id');
            $tagStatement->execute(['image_id' => $id]);

            $qc = new QueryCriteria();
            $qc->delete(ImageEntity::class)
                ->andWhere('id = :id')
                ->withParameters(['id' => $id]);
            $this->execute($qc);

            if ($ownsTransaction) {
                $this->pdo->commit();
            }
        } catch (Throwable $throwable) {
            if ($ownsTransaction && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $throwable;
        }
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
