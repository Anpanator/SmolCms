<?php
declare(strict_types=1);

namespace SmolCms\Test\Service\DB;

use DateTime;
use SmolCms\Data\Constant\ImageStatus;
use SmolCms\Data\Persistence\ImageEntity;
use SmolCms\Service\DB\ImageEntityService;
use SmolCms\TestUtils\Attributes\Autowire;
use SmolCms\TestUtils\FunctionalTestCase;

class ImageEntityServiceTest extends FunctionalTestCase
{
    #[Autowire]
    private ImageEntityService $imageEntityService;

    private function makeEntity(
        ImageStatus $status = ImageStatus::PENDING,
        ?string $checksum = null,
        ?DateTime $uploadDate = null,
    ): ImageEntity
    {
        return new ImageEntity(
            id: null,
            userId: 1,
            uploadDate: $uploadDate ?? new DateTime(),
            status: $status,
            checksum: $checksum ?? hash('sha256', uniqid('image-entity-test', true)),
            originalExtension: 'png',
            thumbFilename: null,
            sampleFilename: null,
            downloadFilename: null,
            width: 100,
            height: 50,
            format: 'png',
            fileSize: 1234,
        );
    }

    public function testSaveAsNew_andFindOneById_roundTripsAllFields(): void
    {
        $uploadDate = new DateTime('2026-01-02 03:04:05');
        $entity = $this->makeEntity(uploadDate: $uploadDate);

        $this->imageEntityService->saveAsNew($entity);

        self::assertNotNull($entity->getId());
        $reloaded = $this->imageEntityService->findOneById($entity->getId());

        self::assertNotNull($reloaded);
        self::assertSame($entity->getId(), $reloaded->getId());
        self::assertSame(1, $reloaded->getUserId());
        self::assertSame('2026-01-02 03:04:05', $reloaded->getUploadDate()->format('Y-m-d H:i:s'));
        self::assertSame(ImageStatus::PENDING, $reloaded->getStatus());
        self::assertSame($entity->getChecksum(), $reloaded->getChecksum());
        self::assertSame('png', $reloaded->getOriginalExtension());
        self::assertNull($reloaded->getThumbFilename());
        self::assertNull($reloaded->getSampleFilename());
        self::assertNull($reloaded->getDownloadFilename());
        self::assertSame(100, $reloaded->getWidth());
        self::assertSame(50, $reloaded->getHeight());
        self::assertSame('png', $reloaded->getFormat());
        self::assertSame(1234, $reloaded->getFileSize());
    }

    public function testEnumMapping_imageStatusIsPersistedAndHydrated(): void
    {
        $entity = $this->makeEntity(status: ImageStatus::PENDING_REVIEW);

        $this->imageEntityService->saveAsNew($entity);
        $reloaded = $this->imageEntityService->findOneById($entity->getId());

        self::assertNotNull($reloaded);
        self::assertSame(ImageStatus::PENDING_REVIEW, $reloaded->getStatus());
    }

    public function testSaveOrUpdate_updatesExistingRow(): void
    {
        $entity = $this->makeEntity();
        $this->imageEntityService->saveAsNew($entity);

        $entity->setStatus(ImageStatus::ACCEPTED);
        $entity->setThumbFilename($entity->getChecksum() . '.avif');
        $this->imageEntityService->saveOrUpdate($entity);

        $reloaded = $this->imageEntityService->findOneById($entity->getId());
        self::assertNotNull($reloaded);
        self::assertSame(ImageStatus::ACCEPTED, $reloaded->getStatus());
        self::assertSame($entity->getChecksum() . '.avif', $reloaded->getThumbFilename());
    }

    public function testFindByStatus_ordersByUploadDateAscending(): void
    {
        $status = ImageStatus::REJECTED;
        $late = $this->makeEntity(status: $status, uploadDate: new DateTime('2026-03-01 12:00:00'));
        $early = $this->makeEntity(status: $status, uploadDate: new DateTime('2026-01-01 12:00:00'));
        $middle = $this->makeEntity(status: $status, uploadDate: new DateTime('2026-02-01 12:00:00'));
        // Different status, must not be returned by findByStatus below
        $otherStatus = $this->makeEntity(status: ImageStatus::ACCEPTED, uploadDate: new DateTime('2026-01-15 12:00:00'));
        foreach ([$late, $early, $middle, $otherStatus] as $entity) {
            $this->imageEntityService->saveAsNew($entity);
        }

        $result = $this->imageEntityService->findByStatus($status);

        self::assertSame([$early->getId(), $middle->getId(), $late->getId()], array_map(
            static fn(ImageEntity $entity) => $entity->getId(),
            $result
        ));
    }

    public function testFindByStatus_appliesLimit(): void
    {
        $status = ImageStatus::PENDING_REVIEW;
        $first = $this->makeEntity(status: $status, uploadDate: new DateTime('2026-01-01 00:00:00'));
        $second = $this->makeEntity(status: $status, uploadDate: new DateTime('2026-01-02 00:00:00'));
        foreach ([$second, $first] as $entity) {
            $this->imageEntityService->saveAsNew($entity);
        }

        $result = $this->imageEntityService->findByStatus($status, 1);

        self::assertSame([$first->getId()], array_map(
            static fn(ImageEntity $entity) => $entity->getId(),
            $result
        ));
    }
}
