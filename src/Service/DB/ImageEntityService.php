<?php
declare(strict_types=1);

namespace SmolCms\Service\DB;

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
        $qc = new QueryCriteria();
        $qc->delete(ImageEntity::class)
            ->andWhere('id = :id')
            ->withParameters(['id' => $id]);
        $this->execute($qc);
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
