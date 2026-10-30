<?php
declare(strict_types=1);

namespace SmolCms\Test\Service\DB;

use DateTime;
use SmolCms\Data\Constant\ImageStatus;
use SmolCms\Data\Persistence\ImageEntity;
use SmolCms\Service\DB\ImageEntityService;
use SmolCms\Service\Tag\TagService;
use SmolCms\TestUtils\Attributes\Autowire;
use SmolCms\TestUtils\FunctionalTestCase;

class ImageEntityServiceTest extends FunctionalTestCase
{
    #[Autowire]
    private ImageEntityService $imageEntityService;

    #[Autowire]
    private TagService $tagService;

    private function makeEntity(
        ImageStatus $status = ImageStatus::PENDING,
        ?string $checksum = null,
        ?DateTime $uploadDate = null,
        int $userId = 1,
        ?string $thumbFilename = null,
        ?DateTime $deletedAt = null,
        string $originalExtension = 'png',
    ): ImageEntity
    {
        return new ImageEntity(
            id: null,
            userId: $userId,
            uploadDate: $uploadDate ?? new DateTime(),
            status: $status,
            checksum: $checksum ?? hash('sha256', uniqid('image-entity-test', true)),
            originalExtension: $originalExtension,
            thumbFilename: $thumbFilename,
            sampleFilename: null,
            downloadFilename: null,
            width: 100,
            height: 50,
            format: 'png',
            fileSize: 1234,
            deletedAt: $deletedAt,
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

    public function testFindByOriginalChecksumReturnsAllRowsForChecksumAndExtension(): void
    {
        $checksum = hash('sha256', 'shared-original');
        $first = $this->makeEntity(checksum: $checksum);
        $second = $this->makeEntity(checksum: $checksum);
        $otherExtension = $this->makeEntity(checksum: $checksum, originalExtension: 'jpeg');
        foreach ([$first, $second, $otherExtension] as $entity) {
            $this->imageEntityService->saveAsNew($entity);
        }

        $matching = $this->imageEntityService->findByOriginalChecksum($checksum, 'png');

        self::assertEqualsCanonicalizing(
            [$first->getId(), $second->getId()],
            array_map(static fn(ImageEntity $entity) => $entity->getId(), $matching),
        );
        self::assertSame([], $this->imageEntityService->findByOriginalChecksum($checksum, 'avif'));
    }

    public function testVisibleImagePageAndCountMatchViewerStatusFilterAndPagination(): void
    {
        $viewerId = 4321;
        $otherUserId = 9876;
        $markerTag = 'visible-page-' . bin2hex(random_bytes(4));
        $this->tagService->createTag($markerTag);
        $olderAccepted = $this->makeEntity(
            status: ImageStatus::ACCEPTED,
            uploadDate: new DateTime('2026-01-01 10:00:00'),
            userId: $otherUserId,
        );
        $ownPending = $this->makeEntity(
            status: ImageStatus::PENDING_REVIEW,
            uploadDate: new DateTime('2026-01-01 12:00:00'),
            userId: $viewerId,
        );
        $otherPending = $this->makeEntity(
            status: ImageStatus::PENDING_REVIEW,
            uploadDate: new DateTime('2026-01-01 13:00:00'),
            userId: $otherUserId,
        );
        $unprocessed = $this->makeEntity(
            status: ImageStatus::PENDING,
            uploadDate: new DateTime('2026-01-01 14:00:00'),
            userId: $viewerId,
        );
        $rejected = $this->makeEntity(
            status: ImageStatus::REJECTED,
            uploadDate: new DateTime('2026-01-01 15:00:00'),
            userId: $otherUserId,
        );
        foreach ([$olderAccepted, $ownPending, $otherPending, $unprocessed, $rejected] as $image) {
            $this->imageEntityService->saveAsNew($image);
            $this->tagService->attachTagToImage($image->getId(), $markerTag);
        }

        self::assertSame(1, $this->imageEntityService->countVisible([$markerTag]));
        self::assertSame(
            [$olderAccepted->getId()],
            array_map(
                static fn(ImageEntity $image) => $image->getId(),
                $this->imageEntityService->findVisiblePage(10, 0, [$markerTag]),
            ),
        );

        self::assertSame(2, $this->imageEntityService->countVisible([$markerTag], $viewerId));
        self::assertSame(
            [$ownPending->getId(), $olderAccepted->getId()],
            array_map(
                static fn(ImageEntity $image) => $image->getId(),
                $this->imageEntityService->findVisiblePage(10, 0, [$markerTag], $viewerId),
            ),
        );

        self::assertSame(3, $this->imageEntityService->countVisible([$markerTag], $viewerId, true));
        self::assertSame(
            [$otherPending->getId(), $ownPending->getId()],
            array_map(
                static fn(ImageEntity $image) => $image->getId(),
                $this->imageEntityService->findVisiblePage(2, 0, [$markerTag], $viewerId, true),
            ),
        );
        self::assertSame(
            [$olderAccepted->getId()],
            array_map(
                static fn(ImageEntity $image) => $image->getId(),
                $this->imageEntityService->findVisiblePage(2, 2, [$markerTag], $viewerId, true),
            ),
        );
    }

    public function testFindRecentByUserFiltersStatusThumbnailAndUserOrdersNewestFirstAndAppliesLimit(): void
    {
        $userId = 4321;
        $otherUserId = 9876;
        $olderAccepted = $this->makeEntity(
            status: ImageStatus::ACCEPTED,
            uploadDate: new DateTime('2026-01-01 12:00:00'),
            userId: $userId,
            thumbFilename: 'older.avif',
        );
        $sameDateAccepted = $this->makeEntity(
            status: ImageStatus::ACCEPTED,
            uploadDate: new DateTime('2026-01-02 12:00:00'),
            userId: $userId,
            thumbFilename: 'same-date-accepted.avif',
        );
        $sameDatePendingReview = $this->makeEntity(
            status: ImageStatus::PENDING_REVIEW,
            uploadDate: new DateTime('2026-01-02 12:00:00'),
            userId: $userId,
            thumbFilename: 'same-date-pending-review.avif',
        );
        $newestAccepted = $this->makeEntity(
            status: ImageStatus::ACCEPTED,
            uploadDate: new DateTime('2026-01-03 12:00:00'),
            userId: $userId,
            thumbFilename: 'newest.avif',
        );
        $otherUserImage = $this->makeEntity(
            status: ImageStatus::ACCEPTED,
            uploadDate: new DateTime('2026-01-04 12:00:00'),
            userId: $otherUserId,
            thumbFilename: 'other-user.avif',
        );
        $pendingWithThumb = $this->makeEntity(
            status: ImageStatus::PENDING,
            uploadDate: new DateTime('2026-01-05 12:00:00'),
            userId: $userId,
            thumbFilename: 'pending.avif',
        );
        $pendingReviewWithoutThumb = $this->makeEntity(
            status: ImageStatus::PENDING_REVIEW,
            uploadDate: new DateTime('2026-01-06 12:00:00'),
            userId: $userId,
        );
        $rejected = $this->makeEntity(
            status: ImageStatus::REJECTED,
            uploadDate: new DateTime('2026-01-07 12:00:00'),
            userId: $userId,
            thumbFilename: 'rejected.avif',
        );
        foreach ([
            $olderAccepted,
            $sameDateAccepted,
            $sameDatePendingReview,
            $newestAccepted,
            $otherUserImage,
            $pendingWithThumb,
            $pendingReviewWithoutThumb,
            $rejected,
        ] as $image) {
            $this->imageEntityService->saveAsNew($image);
        }

        $result = $this->imageEntityService->findRecentByUser(
            $userId,
            3,
            [ImageStatus::PENDING_REVIEW, ImageStatus::ACCEPTED],
        );

        self::assertSame(
            [$newestAccepted->getId(), $sameDatePendingReview->getId(), $sameDateAccepted->getId()],
            array_map(static fn(ImageEntity $image) => $image->getId(), $result),
        );
        self::assertSame([], $this->imageEntityService->findRecentByUser(
            $userId,
            10,
            [],
        ));
        self::assertSame([], $this->imageEntityService->findRecentByUser(
            $userId,
            0,
            [ImageStatus::ACCEPTED],
        ));
    }

    public function testDeletedAtRoundTripsAndFindByStatusExcludesQueuedImagesByDefault(): void
    {
        $deletedAt = new DateTime('2026-02-03 04:05:06');
        $queued = $this->makeEntity(status: ImageStatus::ACCEPTED, deletedAt: $deletedAt);
        $active = $this->makeEntity(status: ImageStatus::ACCEPTED);
        $this->imageEntityService->saveAsNew($queued);
        $this->imageEntityService->saveAsNew($active);

        $reloaded = $this->imageEntityService->findOneById($queued->getId());
        self::assertNotNull($reloaded);
        self::assertSame('2026-02-03 04:05:06', $reloaded->getDeletedAt()?->format('Y-m-d H:i:s'));

        $visibleIds = array_map(
            static fn(ImageEntity $image) => $image->getId(),
            $this->imageEntityService->findByStatus(ImageStatus::ACCEPTED),
        );
        self::assertContains($active->getId(), $visibleIds);
        self::assertNotContains($queued->getId(), $visibleIds);

        $allIds = array_map(
            static fn(ImageEntity $image) => $image->getId(),
            $this->imageEntityService->findByStatus(ImageStatus::ACCEPTED, includeDeleted: true),
        );
        self::assertContains($queued->getId(), $allIds);
        self::assertContains($active->getId(), $allIds);
    }

    public function testDeletionQueueOrdersQueuedImagesByDeletionDateNewestFirst(): void
    {
        $older = $this->makeEntity(status: ImageStatus::ACCEPTED, deletedAt: new DateTime('2026-01-01 10:00:00'));
        $newer = $this->makeEntity(status: ImageStatus::PENDING_REVIEW, deletedAt: new DateTime('2026-02-01 10:00:00'));
        $active = $this->makeEntity(status: ImageStatus::ACCEPTED);
        foreach ([$older, $newer, $active] as $image) {
            $this->imageEntityService->saveAsNew($image);
        }

        $queuedIds = array_map(
            static fn(ImageEntity $image) => $image->getId(),
            $this->imageEntityService->findDeletionQueue(),
        );
        self::assertSame(
            [$newer->getId(), $older->getId()],
            array_values(array_filter(
                $queuedIds,
                static fn(?int $id): bool => in_array($id, [$older->getId(), $newer->getId()], true),
            )),
        );
        self::assertNotContains($active->getId(), $queuedIds);
    }

    public function testVisibleAcceptedAndRecentQueriesExcludeQueuedImages(): void
    {
        $viewerId = 7777;
        $markerTag = 'queued-images-' . bin2hex(random_bytes(4));
        $this->tagService->createTag($markerTag);
        $active = $this->makeEntity(
            status: ImageStatus::ACCEPTED,
            uploadDate: new DateTime('2026-01-01 10:00:00'),
            userId: $viewerId,
            thumbFilename: 'active-thumb.avif',
        );
        $queued = $this->makeEntity(
            status: ImageStatus::ACCEPTED,
            uploadDate: new DateTime('2026-01-02 10:00:00'),
            userId: $viewerId,
            thumbFilename: 'queued-thumb.avif',
            deletedAt: new DateTime('2026-01-03 10:00:00'),
        );
        $ownQueuedPendingReview = $this->makeEntity(
            status: ImageStatus::PENDING_REVIEW,
            uploadDate: new DateTime('2026-01-04 10:00:00'),
            userId: $viewerId,
            thumbFilename: 'queued-pending-thumb.avif',
            deletedAt: new DateTime('2026-01-05 10:00:00'),
        );
        foreach ([$active, $queued, $ownQueuedPendingReview] as $image) {
            $this->imageEntityService->saveAsNew($image);
            $this->tagService->attachTagToImage($image->getId(), $markerTag);
        }
        $this->tagService->attachTagToImage($queued->getId(), 'tagme');
        $this->tagService->attachTagToImage($ownQueuedPendingReview->getId(), 'tagme');

        self::assertSame(1, $this->imageEntityService->countVisible([$markerTag]));
        self::assertSame(
            [$active->getId()],
            array_map(
                static fn(ImageEntity $image) => $image->getId(),
                $this->imageEntityService->findVisiblePage(10, 0, [$markerTag], $viewerId, true),
            ),
        );
        self::assertSame(1, $this->imageEntityService->countAccepted([$markerTag]));
        self::assertSame(
            [$active->getId()],
            array_map(
                static fn(ImageEntity $image) => $image->getId(),
                $this->imageEntityService->findAcceptedPage(10, 0, [$markerTag]),
            ),
        );
        self::assertSame(
            [$active->getId()],
            array_map(
                static fn(ImageEntity $image) => $image->getId(),
                $this->imageEntityService->findRecentByUser(
                    $viewerId,
                    10,
                    [ImageStatus::PENDING_REVIEW, ImageStatus::ACCEPTED],
                ),
            ),
        );
        self::assertNull($this->imageEntityService->findRandomTagmeImage());
    }

    public function testAccessibleNeighborIdsSkipQueuedImagesForEveryViewer(): void
    {
        $first = $this->makeEntity(status: ImageStatus::ACCEPTED, uploadDate: new DateTime('2026-02-01 10:00:00'));
        $queued = $this->makeEntity(
            status: ImageStatus::ACCEPTED,
            uploadDate: new DateTime('2026-02-01 11:00:00'),
            deletedAt: new DateTime('2026-02-02 10:00:00'),
        );
        $last = $this->makeEntity(status: ImageStatus::ACCEPTED, uploadDate: new DateTime('2026-02-01 12:00:00'));
        foreach ([$first, $queued, $last] as $image) {
            $this->imageEntityService->saveAsNew($image);
        }

        self::assertSame(
            $last->getId(),
            $this->imageEntityService->findNextAccessibleId($first->getId(), null, true),
        );
        self::assertSame(
            $first->getId(),
            $this->imageEntityService->findPreviousAccessibleId($last->getId(), null, true),
        );
    }

    public function testAccessibleNeighborIds_followIdOrderSkipInvisibleImagesAndRespectBoundaries(): void
    {
        $viewerId = 5555;
        $otherUserId = 6666;
        $acceptedFirst = $this->makeEntity(status: ImageStatus::ACCEPTED, uploadDate: new DateTime('2026-02-01 10:00:00'), userId: $otherUserId);
        $foreignPendingReview = $this->makeEntity(status: ImageStatus::PENDING_REVIEW, uploadDate: new DateTime('2026-02-01 11:00:00'), userId: $otherUserId);
        $acceptedSecond = $this->makeEntity(status: ImageStatus::ACCEPTED, uploadDate: new DateTime('2026-02-01 12:00:00'), userId: $otherUserId);
        $ownUnprocessed = $this->makeEntity(status: ImageStatus::PENDING, uploadDate: new DateTime('2026-02-01 13:00:00'), userId: $viewerId);
        $ownRejected = $this->makeEntity(status: ImageStatus::REJECTED, uploadDate: new DateTime('2026-02-01 14:00:00'), userId: $viewerId);
        $ownPendingReview = $this->makeEntity(status: ImageStatus::PENDING_REVIEW, uploadDate: new DateTime('2026-02-01 15:00:00'), userId: $viewerId);
        $acceptedLast = $this->makeEntity(status: ImageStatus::ACCEPTED, uploadDate: new DateTime('2026-02-01 16:00:00'), userId: $otherUserId);
        foreach ([$acceptedFirst, $foreignPendingReview, $acceptedSecond, $ownUnprocessed, $ownRejected, $ownPendingReview, $acceptedLast] as $image) {
            $this->imageEntityService->saveAsNew($image);
        }

        // Anonymous viewer: accepted images only, ordered by id in both directions.
        self::assertSame($acceptedSecond->getId(), $this->imageEntityService->findNextAccessibleId($acceptedFirst->getId(), null, false));
        self::assertSame($acceptedLast->getId(), $this->imageEntityService->findNextAccessibleId($acceptedSecond->getId(), null, false));
        self::assertSame($acceptedFirst->getId(), $this->imageEntityService->findPreviousAccessibleId($acceptedSecond->getId(), null, false));
        self::assertNull($this->imageEntityService->findNextAccessibleId(PHP_INT_MAX, null, false));
        self::assertNull($this->imageEntityService->findPreviousAccessibleId(0, null, false));

        // Regular viewer: own pending-review uploads are visible, everything else is skipped.
        self::assertSame($acceptedSecond->getId(), $this->imageEntityService->findNextAccessibleId($acceptedFirst->getId(), $viewerId, false));
        self::assertSame($ownPendingReview->getId(), $this->imageEntityService->findNextAccessibleId($acceptedSecond->getId(), $viewerId, false));
        self::assertSame($acceptedLast->getId(), $this->imageEntityService->findNextAccessibleId($ownPendingReview->getId(), $viewerId, false));
        self::assertSame($ownPendingReview->getId(), $this->imageEntityService->findPreviousAccessibleId($acceptedLast->getId(), $viewerId, false));
        self::assertSame($acceptedSecond->getId(), $this->imageEntityService->findPreviousAccessibleId($ownPendingReview->getId(), $viewerId, false));
        self::assertNull($this->imageEntityService->findNextAccessibleId(PHP_INT_MAX, $viewerId, false));

        // Full-access viewer: every image is reachable, including pending and rejected ones.
        self::assertSame($foreignPendingReview->getId(), $this->imageEntityService->findNextAccessibleId($acceptedFirst->getId(), $viewerId, true));
        self::assertSame($ownUnprocessed->getId(), $this->imageEntityService->findNextAccessibleId($acceptedSecond->getId(), $viewerId, true));
        self::assertSame($ownRejected->getId(), $this->imageEntityService->findNextAccessibleId($ownUnprocessed->getId(), $viewerId, true));
        self::assertSame($ownPendingReview->getId(), $this->imageEntityService->findNextAccessibleId($ownRejected->getId(), $viewerId, true));
        self::assertSame($acceptedLast->getId(), $this->imageEntityService->findNextAccessibleId($ownPendingReview->getId(), $viewerId, true));
        self::assertSame($ownPendingReview->getId(), $this->imageEntityService->findPreviousAccessibleId($acceptedLast->getId(), $viewerId, true));
        self::assertSame($acceptedFirst->getId(), $this->imageEntityService->findPreviousAccessibleId($foreignPendingReview->getId(), $viewerId, true));
        self::assertNull($this->imageEntityService->findPreviousAccessibleId(0, $viewerId, true));
    }
}
