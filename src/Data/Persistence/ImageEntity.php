<?php
declare(strict_types=1);

namespace SmolCms\Data\Persistence;

use DateTime;
use SmolCms\Data\Constant\ImageStatus;
use SmolCms\Service\DB\Attribute\Entity;
use SmolCms\Service\DB\Attribute\Id;

#[Entity(table: 'image')]
class ImageEntity
{
    public function __construct(
        #[Id]
        private ?int          $id,
        private int           $userId,
        private DateTime      $uploadDate,
        private ImageStatus   $status,
        private string        $checksum,
        private string        $originalExtension,
        private ?string       $thumbFilename = null,
        private ?string       $sampleFilename = null,
        private ?string       $downloadFilename = null,
        private ?int          $width = null,
        private ?int          $height = null,
        private ?string       $format = null,
        private ?int          $fileSize = null,
        private ?DateTime     $deletedAt = null,
    )
    {
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(?int $id): void
    {
        $this->id = $id;
    }

    public function getUserId(): int
    {
        return $this->userId;
    }

    public function setUserId(int $userId): void
    {
        $this->userId = $userId;
    }

    public function getUploadDate(): DateTime
    {
        return $this->uploadDate;
    }

    public function setUploadDate(DateTime $uploadDate): void
    {
        $this->uploadDate = $uploadDate;
    }

    public function getStatus(): ImageStatus
    {
        return $this->status;
    }

    public function setStatus(ImageStatus $status): void
    {
        $this->status = $status;
    }

    public function getChecksum(): string
    {
        return $this->checksum;
    }

    public function setChecksum(string $checksum): void
    {
        $this->checksum = $checksum;
    }

    public function getOriginalExtension(): string
    {
        return $this->originalExtension;
    }

    public function setOriginalExtension(string $originalExtension): void
    {
        $this->originalExtension = $originalExtension;
    }

    public function getThumbFilename(): ?string
    {
        return $this->thumbFilename;
    }

    public function setThumbFilename(?string $thumbFilename): void
    {
        $this->thumbFilename = $thumbFilename;
    }

    public function getSampleFilename(): ?string
    {
        return $this->sampleFilename;
    }

    public function setSampleFilename(?string $sampleFilename): void
    {
        $this->sampleFilename = $sampleFilename;
    }

    public function getDownloadFilename(): ?string
    {
        return $this->downloadFilename;
    }

    public function setDownloadFilename(?string $downloadFilename): void
    {
        $this->downloadFilename = $downloadFilename;
    }

    public function getWidth(): ?int
    {
        return $this->width;
    }

    public function setWidth(?int $width): void
    {
        $this->width = $width;
    }

    public function getHeight(): ?int
    {
        return $this->height;
    }

    public function setHeight(?int $height): void
    {
        $this->height = $height;
    }

    public function getFormat(): ?string
    {
        return $this->format;
    }

    public function setFormat(?string $format): void
    {
        $this->format = $format;
    }

    public function getFileSize(): ?int
    {
        return $this->fileSize;
    }

    public function setFileSize(?int $fileSize): void
    {
        $this->fileSize = $fileSize;
    }

    /**
     * Timestamp of when a moderator moved the image to the delete queue, or
     * null while the image is part of the regular collection.
     */
    public function getDeletedAt(): ?DateTime
    {
        return $this->deletedAt;
    }

    public function setDeletedAt(?DateTime $deletedAt): void
    {
        $this->deletedAt = $deletedAt;
    }

    public function isDeleted(): bool
    {
        return $this->deletedAt !== null;
    }

    /** Hides the image from every regular view and queues it for deletion. */
    public function queueForDeletion(): void
    {
        $this->deletedAt = new DateTime();
    }

    /** Returns the queued image to the regular collection. */
    public function restoreFromDeletionQueue(): void
    {
        $this->deletedAt = null;
    }
}
