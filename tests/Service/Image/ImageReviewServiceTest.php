<?php

declare(strict_types=1);

namespace SmolCms\Test\Service\Image;

use DateTime;
use SmolCms\Data\Constant\AccessLevel;
use SmolCms\Data\Constant\ImageStatus;
use SmolCms\Data\Persistence\UserEntity;
use SmolCms\Service\DB\ImageEntityService;
use SmolCms\Service\DB\UserService;
use SmolCms\Service\Image\ImagePathLayout;
use SmolCms\Service\Image\ImageQueryService;
use SmolCms\Service\Image\ImageReviewService;
use SmolCms\TestUtils\Attributes\Autowire;
use SmolCms\TestUtils\FunctionalTestCase;
use SmolCms\TestUtils\ImageTestUtils;

class ImageReviewServiceTest extends FunctionalTestCase
{
    #[Autowire]
    private ImageReviewService $imageReviewService;

    #[Autowire]
    private ImageEntityService $imageEntityService;

    #[Autowire]
    private ImageQueryService $imageQueryService;

    #[Autowire]
    private UserService $userService;

    public function testReviewQueueMapsPendingReviewImagesAndUploader(): void
    {
        $user = $this->createUser('image-review-queue-' . bin2hex(random_bytes(4)), 'Review Queue Uploader');
        $thumbFilename = $this->storedFileName('review-thumb');
        $sampleFilename = $this->storedFileName('review-sample');
        $image = ImageTestUtils::createImageEntity(
            $this->imageEntityService,
            userId: $user->getId(),
            status: ImageStatus::PENDING_REVIEW,
            uploadDate: new DateTime('2026-10-05 06:07:08'),
            thumbFilename: $thumbFilename,
            sampleFilename: $sampleFilename,
        );

        $matchingQueueEntry = array_values(array_filter(
            $this->imageReviewService->getReviewQueue(),
            static fn(array $entry): bool => $entry['id'] === $image->getId(),
        ));

        self::assertCount(1, $matchingQueueEntry);
        self::assertSame(
            [
                'id' => $image->getId(),
                'thumbSrc' => $this->imageQueryService->getThumbSrc($image),
                'sampleSrc' => $this->imageQueryService->getSampleSrc($image),
                'uploadDate' => '2026-10-05 06:07:08',
                'uploaderId' => $user->getId(),
                'uploader' => 'Review Queue Uploader',
            ],
            $matchingQueueEntry[0],
        );
    }

    public function testDeletionQueueMapsQueuedImagesAndExcludesActiveOnes(): void
    {
        $user = $this->createUser('image-delete-queue-' . bin2hex(random_bytes(4)), 'Delete Queue Uploader');
        $thumbFilename = $this->storedFileName('delete-thumb');
        $sampleFilename = $this->storedFileName('delete-sample');
        $queued = ImageTestUtils::createImageEntity(
            $this->imageEntityService,
            userId: $user->getId(),
            status: ImageStatus::ACCEPTED,
            uploadDate: new DateTime('2026-10-05 06:07:08'),
            thumbFilename: $thumbFilename,
            sampleFilename: $sampleFilename,
            deletedAt: new DateTime('2026-10-06 06:07:08'),
        );
        ImageTestUtils::createImageEntity(
            $this->imageEntityService,
            userId: $user->getId(),
            status: ImageStatus::ACCEPTED,
        );

        $entries = array_values(array_filter(
            $this->imageReviewService->getDeletionQueue(),
            static fn(array $entry): bool => $entry['id'] === $queued->getId(),
        ));

        self::assertCount(1, $entries);
        self::assertSame(
            [
                'id' => $queued->getId(),
                'thumbSrc' => $this->imageQueryService->getThumbSrc($queued),
                'sampleSrc' => $this->imageQueryService->getSampleSrc($queued),
                'uploadDate' => '2026-10-05 06:07:08',
                'uploaderId' => $user->getId(),
                'uploader' => 'Delete Queue Uploader',
            ],
            $entries[0],
        );
    }

    public function testQueueForDeletionAndRestoreToggleTheDeletionMarker(): void
    {
        $image = ImageTestUtils::createImageEntity(
            $this->imageEntityService,
            userId: 1,
            status: ImageStatus::ACCEPTED,
        );
        self::assertFalse($image->isDeleted());

        self::assertFalse($this->imageReviewService->queueForDeletion(PHP_INT_MAX));
        self::assertTrue($this->imageReviewService->queueForDeletion($image->getId()));
        $queued = $this->imageEntityService->findOneById($image->getId());
        self::assertNotNull($queued);
        self::assertTrue($queued->isDeleted());

        // Queueing an already queued image is a no-op success and keeps its timestamp.
        $deletedAt = $queued->getDeletedAt()?->format('Y-m-d H:i:s');
        self::assertTrue($this->imageReviewService->queueForDeletion($image->getId()));
        self::assertSame(
            $deletedAt,
            $this->imageEntityService->findOneById($image->getId())?->getDeletedAt()?->format('Y-m-d H:i:s'),
        );

        self::assertFalse($this->imageReviewService->restore(PHP_INT_MAX));
        self::assertTrue($this->imageReviewService->restore($image->getId()));
        self::assertFalse($this->imageEntityService->findOneById($image->getId())?->isDeleted());

        // Restoring an image that is not queued is a no-op success.
        self::assertTrue($this->imageReviewService->restore($image->getId()));
    }

    public function testReviewQueueExcludesImagesQueuedForDeletion(): void
    {
        $visible = ImageTestUtils::createImageEntity(
            $this->imageEntityService,
            userId: 1,
            status: ImageStatus::PENDING_REVIEW,
        );
        $queued = ImageTestUtils::createImageEntity(
            $this->imageEntityService,
            userId: 1,
            status: ImageStatus::PENDING_REVIEW,
            deletedAt: new DateTime('2026-10-20 09:00:00'),
        );

        $ids = array_column($this->imageReviewService->getReviewQueue(), 'id');

        self::assertContains($visible->getId(), $ids);
        self::assertNotContains($queued->getId(), $ids);
    }

    public function testDeleteQueuedRequiresTheImageToBeInTheDeleteQueue(): void
    {
        $active = ImageTestUtils::createImageEntity(
            $this->imageEntityService,
            userId: 1,
            status: ImageStatus::ACCEPTED,
        );
        $queued = ImageTestUtils::createImageEntity(
            $this->imageEntityService,
            userId: 1,
            status: ImageStatus::ACCEPTED,
            deletedAt: new DateTime('2026-10-20 09:00:00'),
        );

        self::assertFalse($this->imageReviewService->deleteQueued($active->getId()));
        self::assertNotNull($this->imageEntityService->findOneById($active->getId()));

        self::assertFalse($this->imageReviewService->deleteQueued(PHP_INT_MAX));
        self::assertTrue($this->imageReviewService->deleteQueued($queued->getId()));
        self::assertNull($this->imageEntityService->findOneById($queued->getId()));
    }

    public function testApproveTransitionsPendingReviewAndIsIdempotentForAcceptedImages(): void
    {
        $pending = ImageTestUtils::createImageEntity(
            $this->imageEntityService,
            userId: 1,
            status: ImageStatus::PENDING_REVIEW,
        );
        $accepted = ImageTestUtils::createImageEntity(
            $this->imageEntityService,
            userId: 1,
            status: ImageStatus::ACCEPTED,
        );

        self::assertFalse($this->imageReviewService->approve(PHP_INT_MAX));
        self::assertTrue($this->imageReviewService->approve($pending->getId()));
        self::assertSame(ImageStatus::ACCEPTED, $this->imageEntityService->findOneById($pending->getId())?->getStatus());
        self::assertTrue($this->imageReviewService->approve($accepted->getId()));
        self::assertSame(ImageStatus::ACCEPTED, $this->imageEntityService->findOneById($accepted->getId())?->getStatus());
    }

    public function testRejectLogsFileDeletionFailureAndStillDeletesDatabaseRow(): void
    {
        $checksum = ImageTestUtils::randomChecksum();
        $image = ImageTestUtils::createImageEntity(
            $this->imageEntityService,
            userId: 1,
            status: ImageStatus::PENDING_REVIEW,
            checksum: $checksum,
        );
        $originalPath = IMAGE_STORAGE_DIR . '/' . ImagePathLayout::relativePath($checksum, 'jpeg');
        $targetPath = ROOT_DIR . '/private/review-file-target-' . bin2hex(random_bytes(4)) . '.txt';
        $logBefore = file_get_contents(ERROR_LOG_FILE_PATH);
        self::assertIsString($logBefore);

        if (!is_dir(dirname($originalPath)) && !mkdir(dirname($originalPath), 0777, true) && !is_dir(dirname($originalPath))) {
            self::fail('Could not create the image-storage shard for the rejection test.');
        }
        self::assertNotFalse(file_put_contents($targetPath, 'outside the app-level whitelist'));
        self::assertTrue(symlink($targetPath, $originalPath));

        try {
            self::assertTrue($this->imageReviewService->reject($image->getId()));
            self::assertNull($this->imageEntityService->findOneById($image->getId()));
            self::assertTrue(is_link($originalPath));

            $logAfter = file_get_contents(ERROR_LOG_FILE_PATH);
            self::assertIsString($logAfter);
            $newLogEntries = substr($logAfter, strlen($logBefore));
            self::assertStringContainsString('[WARNING]', $newLogEntries);
            self::assertStringContainsString('Could not delete image file', $newLogEntries);
            self::assertStringContainsString($originalPath, $newLogEntries);
        } finally {
            if (is_link($originalPath)) {
                self::assertTrue(unlink($originalPath));
            }
            if (is_file($targetPath)) {
                self::assertTrue(unlink($targetPath));
            }
            if (is_dir(dirname($originalPath)) && count(scandir(dirname($originalPath)) ?: []) === 2) {
                self::assertTrue(rmdir(dirname($originalPath)));
            }
        }
    }

    private function createUser(string $loginName, string $displayName): UserEntity
    {
        $user = new UserEntity(
            id: null,
            loginName: $loginName,
            password: 'hashed-password',
            displayName: $displayName,
            state: 'active',
            registerDate: new DateTime('2026-10-20 12:00:00'),
            lastLoginDate: null,
            accessLevel: AccessLevel::NOVICE,
        );
        $this->userService->saveAsNew($user);
        return $user;
    }

    private function storedFileName(string $label): string
    {
        return bin2hex(random_bytes(16)) . '-' . $label . '.jpeg';
    }
}
