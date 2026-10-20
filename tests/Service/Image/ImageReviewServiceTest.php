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
                'uploader' => 'Review Queue Uploader',
            ],
            $matchingQueueEntry[0],
        );
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
