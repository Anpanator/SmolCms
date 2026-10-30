<?php
declare(strict_types=1);

namespace SmolCms\Test\Controller;

use DateTime;
use PHPUnit\Framework\Attributes\DataProvider;
use SmolCms\Data\Business\Url;
use SmolCms\Data\Constant\AccessLevel;
use SmolCms\Data\Constant\AuditActionType;
use SmolCms\Data\Constant\HttpContentType;
use SmolCms\Data\Constant\HttpHeader;
use SmolCms\Data\Constant\HttpMethod;
use SmolCms\Data\Constant\HttpStatus;
use SmolCms\Data\Constant\ImageStatus;
use SmolCms\Data\Request\Request;
use SmolCms\Data\Response\Response;
use SmolCms\Service\Core\Session\SessionService;
use SmolCms\Service\DB\AuditLogService;
use SmolCms\Service\DB\ImageEntityService;
use SmolCms\TestUtils\Attributes\Autowire;
use SmolCms\TestUtils\FunctionalTestCase;
use SmolCms\TestUtils\ImageTestUtils;
use SmolCms\TestUtils\LoginWithAccessLevel;

class ImageReviewControllerTest extends FunctionalTestCase
{
    use LoginWithAccessLevel;

    #[Autowire]
    private ImageEntityService $imageEntityService;

    #[Autowire]
    private SessionService $sessionService;

    #[Autowire]
    private AuditLogService $auditLogService;

    protected function setUp(): void
    {
        parent::setUp();
        ImageTestUtils::cleanImageDirs();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        foreach (ImageStatus::cases() as $status) {
            foreach ($this->imageEntityService->findByStatus($status, includeDeleted: true) as $image) {
                $imageId = $image->getId();
                if ($imageId !== null) {
                    $this->imageEntityService->deleteById($imageId);
                }
            }
        }
        ImageTestUtils::cleanImageDirs();
    }

    public static function malformedIdProvider(): array
    {
        return [
            'missing id' => [null],
            'empty id' => [''],
            'non-numeric id' => ['abc'],
            'zero id' => ['0'],
            'negative id' => ['-5'],
            'float id' => ['1.5'],
        ];
    }

    public function testQueueAction_Anonymous_ReturnsUnauthorized(): void
    {
        $response = $this->simulateQueueRequest();

        $this->assertSame(HttpStatus::UNAUTHORIZED, $response->getStatus());
        $this->assertNull($response->getContent());
    }

    public function testQueueAction_Novice_ReturnsForbidden(): void
    {
        $this->loginUser('novice-user', 'Novice User');

        $response = $this->simulateQueueRequest();

        $this->assertSame(HttpStatus::FORBIDDEN, $response->getStatus());
        $this->assertNull($response->getContent());
    }

    public function testQueueAction_ReturnsPendingReviewImagesOldestFirst(): void
    {
        $this->loginUser('uploader-user', 'Upload User');
        $uploader = $this->sessionService->getUserData();
        $this->assertNotNull($uploader);
        $uploaderId = $uploader->id;

        $olderThumbFilename = ImageTestUtils::randomChecksum() . '.avif';
        $older = ImageTestUtils::createImageEntity(
            $this->imageEntityService,
            userId: $uploaderId,
            status: ImageStatus::PENDING_REVIEW,
            uploadDate: new DateTime('2026-10-19 09:00:00'),
            thumbFilename: $olderThumbFilename,
            sampleFilename: ImageTestUtils::randomChecksum() . '.webp',
        );
        $newerThumbFilename = ImageTestUtils::randomChecksum() . '.avif';
        $newer = ImageTestUtils::createImageEntity(
            $this->imageEntityService,
            userId: $uploaderId,
            status: ImageStatus::PENDING_REVIEW,
            uploadDate: new DateTime('2026-10-19 11:00:00'),
            thumbFilename: $newerThumbFilename,
            sampleFilename: ImageTestUtils::randomChecksum() . '.webp',
        );
        ImageTestUtils::writeImageFile(IMAGE_THUMB_DIR, $olderThumbFilename);
        ImageTestUtils::writeImageFile(IMAGE_THUMB_DIR, $newerThumbFilename);
        ImageTestUtils::createImageEntity(
            $this->imageEntityService,
            userId: $uploaderId,
            status: ImageStatus::ACCEPTED,
            uploadDate: new DateTime('2026-10-19 10:00:00'),
        );

        $this->loginUserWithAccessLevel('mod-user', 'Mod User', AccessLevel::MOD);

        $response = $this->simulateQueueRequest();

        $this->assertSame(HttpStatus::OK, $response->getStatus());
        $this->assertSame(HttpContentType::JSON->value, $response->getHeaders()[HttpHeader::CONTENT_TYPE->value]);
        $this->assertSame(
            [
                'images' => [
                    [
                        'id' => $older->getId(),
                        'thumbSrc' => '/public/img/thumb/' . substr($older->getThumbFilename(), 0, 2) . '/' . $older->getThumbFilename(),
                        'sampleSrc' => '/api/image/sample?name=' . $older->getSampleFilename(),
                        'uploadDate' => '2026-10-19 09:00:00',
                        'uploaderId' => $uploaderId,
                        'uploader' => 'Upload User',
                    ],
                    [
                        'id' => $newer->getId(),
                        'thumbSrc' => '/public/img/thumb/' . substr($newer->getThumbFilename(), 0, 2) . '/' . $newer->getThumbFilename(),
                        'sampleSrc' => '/api/image/sample?name=' . $newer->getSampleFilename(),
                        'uploadDate' => '2026-10-19 11:00:00',
                        'uploaderId' => $uploaderId,
                        'uploader' => 'Upload User',
                    ],
                ],
            ],
            json_decode((string)$response->getContent(), true, JSON_THROW_ON_ERROR)
        );
    }

    public function testApproveAction_Anonymous_ReturnsUnauthorized(): void
    {
        $response = $this->simulateReviewPostRequest('/api/review/approve', '1');

        $this->assertSame(HttpStatus::UNAUTHORIZED, $response->getStatus());
    }

    public function testApproveAction_Novice_ReturnsForbidden(): void
    {
        $this->loginUser('novice-user', 'Novice User');

        $response = $this->simulateReviewPostRequest('/api/review/approve', '1');

        $this->assertSame(HttpStatus::FORBIDDEN, $response->getStatus());
    }

    public function testApproveAction_PendingReviewImage_SetsStatusAccepted(): void
    {
        $this->loginUserWithAccessLevel('mod-user', 'Mod User', AccessLevel::MOD);
        $entity = ImageTestUtils::createImageEntity(
            $this->imageEntityService,
            userId: 1,
            status: ImageStatus::PENDING_REVIEW,
        );

        $response = $this->simulateReviewPostRequest('/api/review/approve', (string)$entity->getId());

        $this->assertSame(HttpStatus::NO_CONTENT, $response->getStatus());
        $this->assertNull($response->getContent());
        $storedEntity = $this->imageEntityService->findOneById($entity->getId());
        $this->assertNotNull($storedEntity);
        $this->assertSame(ImageStatus::ACCEPTED, $storedEntity->getStatus());
    }

    public function testApproveAction_AlreadyAcceptedImage_IsIdempotentNoOp(): void
    {
        $this->loginUserWithAccessLevel('mod-user', 'Mod User', AccessLevel::MOD);
        $entity = ImageTestUtils::createImageEntity(
            $this->imageEntityService,
            userId: 1,
            status: ImageStatus::ACCEPTED,
        );

        $response = $this->simulateReviewPostRequest('/api/review/approve', (string)$entity->getId());

        // Approving a non-PENDING_REVIEW image is an idempotent no-op: 204, status unchanged.
        $this->assertSame(HttpStatus::NO_CONTENT, $response->getStatus());
        $storedEntity = $this->imageEntityService->findOneById($entity->getId());
        $this->assertNotNull($storedEntity);
        $this->assertSame(ImageStatus::ACCEPTED, $storedEntity->getStatus());
    }

    public function testApproveAction_UnknownId_ReturnsNotFound(): void
    {
        $this->loginUserWithAccessLevel('mod-user', 'Mod User', AccessLevel::MOD);

        $response = $this->simulateReviewPostRequest('/api/review/approve', '99999');

        $this->assertSame(HttpStatus::NOT_FOUND, $response->getStatus());
        $this->assertNull($response->getContent());
    }

    #[DataProvider('malformedIdProvider')]
    public function testApproveAction_MalformedId_ReturnsBadRequest(?string $id): void
    {
        $this->loginUserWithAccessLevel('mod-user', 'Mod User', AccessLevel::MOD);

        $response = $this->simulateReviewPostRequest('/api/review/approve', $id);

        $this->assertSame(HttpStatus::BAD_REQUEST, $response->getStatus());
        $this->assertNull($response->getContent());
    }

    public function testRejectAction_Anonymous_ReturnsUnauthorized(): void
    {
        $response = $this->simulateReviewPostRequest('/api/review/reject', '1');

        $this->assertSame(HttpStatus::UNAUTHORIZED, $response->getStatus());
    }

    public function testRejectAction_Novice_ReturnsForbidden(): void
    {
        $this->loginUser('novice-user', 'Novice User');

        $response = $this->simulateReviewPostRequest('/api/review/reject', '1');

        $this->assertSame(HttpStatus::FORBIDDEN, $response->getStatus());
    }

    public function testRejectAction_PendingReviewImage_DeletesRowAndAllFiles(): void
    {
        $this->loginUserWithAccessLevel('mod-user', 'Mod User', AccessLevel::MOD);
        $checksum = ImageTestUtils::randomChecksum();
        $thumbFilename = ImageTestUtils::randomChecksum() . '.avif';
        $sampleFilename = ImageTestUtils::randomChecksum() . '.webp';
        $downloadFilename = ImageTestUtils::randomChecksum() . '.jpeg';
        $entity = ImageTestUtils::createImageEntity(
            $this->imageEntityService,
            userId: 1,
            status: ImageStatus::PENDING_REVIEW,
            checksum: $checksum,
            thumbFilename: $thumbFilename,
            sampleFilename: $sampleFilename,
            downloadFilename: $downloadFilename,
        );
        $originalPath = ImageTestUtils::imageFilePath(IMAGE_STORAGE_DIR, "$checksum.jpeg");
        $thumbPath = ImageTestUtils::imageFilePath(IMAGE_THUMB_DIR, $thumbFilename);
        $samplePath = ImageTestUtils::imageFilePath(IMAGE_SAMPLE_DIR, $sampleFilename);
        $downloadPath = ImageTestUtils::imageFilePath(IMAGE_DOWNLOAD_DIR, $downloadFilename);
        foreach ([$originalPath, $thumbPath, $samplePath, $downloadPath] as $path) {
            $pathDir = dirname($path);
            if (!is_dir($pathDir)) {
                mkdir($pathDir, 0777, true);
            }
            file_put_contents($path, 'dummy');
            $this->assertFileExists($path);
        }

        $response = $this->simulateReviewPostRequest('/api/review/reject', (string)$entity->getId());

        $this->assertSame(HttpStatus::NO_CONTENT, $response->getStatus());
        $this->assertNull($response->getContent());
        $this->assertNull($this->imageEntityService->findOneById($entity->getId()));
        $this->assertFileDoesNotExist($originalPath);
        $this->assertFileDoesNotExist($thumbPath);
        $this->assertFileDoesNotExist($samplePath);
        $this->assertFileDoesNotExist($downloadPath);
    }

    public function testRejectAction_ToleratesAlreadyMissingFiles(): void
    {
        $this->loginUserWithAccessLevel('mod-user', 'Mod User', AccessLevel::MOD);
        $thumbFilename = ImageTestUtils::randomChecksum() . '.avif';
        $entity = ImageTestUtils::createImageEntity(
            $this->imageEntityService,
            userId: 1,
            status: ImageStatus::PENDING_REVIEW,
            thumbFilename: $thumbFilename,
        );
        // Only the thumb exists; original, sample and download files are missing on disk.
        $thumbPath = ImageTestUtils::imageFilePath(IMAGE_THUMB_DIR, $thumbFilename);
        ImageTestUtils::writeImageFile(IMAGE_THUMB_DIR, $thumbFilename);

        $response = $this->simulateReviewPostRequest('/api/review/reject', (string)$entity->getId());

        $this->assertSame(HttpStatus::NO_CONTENT, $response->getStatus());
        $this->assertNull($this->imageEntityService->findOneById($entity->getId()));
        $this->assertFileDoesNotExist($thumbPath);
    }

    public function testRejectAction_KeepsOtherImagesFiles(): void
    {
        $this->loginUserWithAccessLevel('mod-user', 'Mod User', AccessLevel::MOD);
        $otherSampleFilename = ImageTestUtils::randomChecksum() . '.webp';
        $rejectedEntity = ImageTestUtils::createImageEntity(
            $this->imageEntityService,
            userId: 1,
            status: ImageStatus::PENDING_REVIEW,
            sampleFilename: ImageTestUtils::randomChecksum() . '.webp',
        );
        $otherEntity = ImageTestUtils::createImageEntity(
            $this->imageEntityService,
            userId: 1,
            status: ImageStatus::PENDING_REVIEW,
            sampleFilename: $otherSampleFilename,
        );
        $rejectedSamplePath = ImageTestUtils::imageFilePath(IMAGE_SAMPLE_DIR, (string)$rejectedEntity->getSampleFilename());
        $otherSamplePath = ImageTestUtils::imageFilePath(IMAGE_SAMPLE_DIR, $otherSampleFilename);
        ImageTestUtils::writeImageFile(IMAGE_SAMPLE_DIR, (string)$rejectedEntity->getSampleFilename());
        ImageTestUtils::writeImageFile(IMAGE_SAMPLE_DIR, $otherSampleFilename);

        $response = $this->simulateReviewPostRequest('/api/review/reject', (string)$rejectedEntity->getId());

        $this->assertSame(HttpStatus::NO_CONTENT, $response->getStatus());
        $this->assertFileDoesNotExist($rejectedSamplePath);
        $this->assertFileExists($otherSamplePath);
        $this->assertNotNull($this->imageEntityService->findOneById($otherEntity->getId()));
    }

    public function testRejectAction_KeepsContentAddressedFilesReferencedByAnotherImage(): void
    {
        $this->loginUserWithAccessLevel('mod-shared-file-user', 'Shared File Mod', AccessLevel::MOD);
        $checksum = ImageTestUtils::randomChecksum();
        $thumbFilename = ImageTestUtils::randomChecksum() . '.avif';
        $sampleFilename = ImageTestUtils::randomChecksum() . '.webp';
        $downloadFilename = ImageTestUtils::randomChecksum() . '.jpeg';
        $rejectedEntity = ImageTestUtils::createImageEntity(
            $this->imageEntityService,
            userId: 1,
            status: ImageStatus::PENDING_REVIEW,
            checksum: $checksum,
            thumbFilename: $thumbFilename,
            sampleFilename: $sampleFilename,
            downloadFilename: $downloadFilename,
        );
        $survivingEntity = ImageTestUtils::createImageEntity(
            $this->imageEntityService,
            userId: 1,
            status: ImageStatus::PENDING_REVIEW,
            checksum: $checksum,
            thumbFilename: $thumbFilename,
            sampleFilename: $sampleFilename,
            downloadFilename: $downloadFilename,
        );
        $paths = [
            ImageTestUtils::imageFilePath(IMAGE_STORAGE_DIR, "$checksum.jpeg"),
            ImageTestUtils::imageFilePath(IMAGE_THUMB_DIR, $thumbFilename),
            ImageTestUtils::imageFilePath(IMAGE_SAMPLE_DIR, $sampleFilename),
            ImageTestUtils::imageFilePath(IMAGE_DOWNLOAD_DIR, $downloadFilename),
        ];
        foreach ($paths as $path) {
            $pathDir = dirname($path);
            if (!is_dir($pathDir)) {
                mkdir($pathDir, 0777, true);
            }
            file_put_contents($path, 'shared-content');
        }

        $response = $this->simulateReviewPostRequest('/api/review/reject', (string)$rejectedEntity->getId());

        self::assertSame(HttpStatus::NO_CONTENT, $response->getStatus());
        self::assertNull($this->imageEntityService->findOneById($rejectedEntity->getId()));
        self::assertNotNull($this->imageEntityService->findOneById($survivingEntity->getId()));
        foreach ($paths as $path) {
            self::assertFileExists($path);
        }
    }

    public function testRejectAction_UnknownId_ReturnsNotFound(): void
    {
        $this->loginUserWithAccessLevel('mod-user', 'Mod User', AccessLevel::MOD);

        $response = $this->simulateReviewPostRequest('/api/review/reject', '99999');

        $this->assertSame(HttpStatus::NOT_FOUND, $response->getStatus());
        $this->assertNull($response->getContent());
    }

    #[DataProvider('malformedIdProvider')]
    public function testRejectAction_MalformedId_ReturnsBadRequest(?string $id): void
    {
        $this->loginUserWithAccessLevel('mod-user', 'Mod User', AccessLevel::MOD);

        $response = $this->simulateReviewPostRequest('/api/review/reject', $id);

        $this->assertSame(HttpStatus::BAD_REQUEST, $response->getStatus());
        $this->assertNull($response->getContent());
    }

    public function testDeleteAction_Anonymous_ReturnsUnauthorized(): void
    {
        $response = $this->simulateReviewPostRequest('/api/image/delete', '1');

        $this->assertSame(HttpStatus::UNAUTHORIZED, $response->getStatus());
        $this->assertNull($response->getContent());
    }

    public function testDeleteAction_Novice_ReturnsForbidden(): void
    {
        $this->loginUser('novice-user', 'Novice User');

        $response = $this->simulateReviewPostRequest('/api/image/delete', '1');

        $this->assertSame(HttpStatus::FORBIDDEN, $response->getStatus());
        $this->assertNull($response->getContent());
    }

    public function testDeleteAction_AcceptedImage_MovesItToTheDeleteQueueWithoutDeletingFiles(): void
    {
        $this->loginUserWithAccessLevel('mod-user', 'Mod User', AccessLevel::MOD);
        $checksum = ImageTestUtils::randomChecksum();
        $entity = ImageTestUtils::createImageEntity(
            $this->imageEntityService,
            userId: 1,
            status: ImageStatus::ACCEPTED,
            checksum: $checksum,
            thumbFilename: ImageTestUtils::randomChecksum() . '.avif',
            sampleFilename: ImageTestUtils::randomChecksum() . '.webp',
            downloadFilename: ImageTestUtils::randomChecksum() . '.jpeg',
        );
        $originalPath = ImageTestUtils::imageFilePath(IMAGE_STORAGE_DIR, "$checksum.jpeg");
        $thumbPath = ImageTestUtils::imageFilePath(IMAGE_THUMB_DIR, (string)$entity->getThumbFilename());
        $samplePath = ImageTestUtils::imageFilePath(IMAGE_SAMPLE_DIR, (string)$entity->getSampleFilename());
        $downloadPath = ImageTestUtils::imageFilePath(IMAGE_DOWNLOAD_DIR, (string)$entity->getDownloadFilename());
        foreach ([$originalPath, $thumbPath, $samplePath, $downloadPath] as $path) {
            $pathDir = dirname($path);
            if (!is_dir($pathDir)) {
                mkdir($pathDir, 0777, true);
            }
            file_put_contents($path, 'dummy');
            $this->assertFileExists($path);
        }

        $response = $this->simulateReviewPostRequest('/api/image/delete', (string)$entity->getId());

        $this->assertSame(HttpStatus::NO_CONTENT, $response->getStatus());
        $this->assertNull($response->getContent());
        $storedEntity = $this->imageEntityService->findOneById($entity->getId());
        $this->assertNotNull($storedEntity);
        $this->assertTrue($storedEntity->isDeleted());
        $this->assertFileExists($originalPath);
        $this->assertFileExists($thumbPath);
        $this->assertFileExists($samplePath);
        $this->assertFileExists($downloadPath);
    }

    public function testDeleteAction_AlreadyQueuedImage_IsIdempotent(): void
    {
        $this->loginUserWithAccessLevel('mod-user', 'Mod User', AccessLevel::MOD);
        $entity = ImageTestUtils::createImageEntity(
            $this->imageEntityService,
            userId: 1,
            status: ImageStatus::ACCEPTED,
            deletedAt: new DateTime('2026-10-20 10:00:00'),
        );

        $response = $this->simulateReviewPostRequest('/api/image/delete', (string)$entity->getId());

        $this->assertSame(HttpStatus::NO_CONTENT, $response->getStatus());
        $storedEntity = $this->imageEntityService->findOneById($entity->getId());
        $this->assertNotNull($storedEntity);
        $this->assertSame('2026-10-20 10:00:00', $storedEntity->getDeletedAt()?->format('Y-m-d H:i:s'));
    }

    public function testDeleteAction_WritesQueueAuditLogEntryForTheMod(): void
    {
        $this->loginUserWithAccessLevel('audit-mod-user', 'Audit Mod User', AccessLevel::MOD);
        $mod = $this->sessionService->getUserData();
        $this->assertNotNull($mod);
        $entity = ImageTestUtils::createImageEntity(
            $this->imageEntityService,
            userId: 1,
            status: ImageStatus::ACCEPTED,
        );

        $response = $this->simulateReviewPostRequest('/api/image/delete', (string)$entity->getId());

        $this->assertSame(HttpStatus::NO_CONTENT, $response->getStatus());
        $entries = array_values(array_filter(
            $this->auditLogService->getEntries(1, '')['entries'],
            static fn(array $entry): bool => $entry['actionType'] === AuditActionType::IMAGE_QUEUED_FOR_DELETION->value
                && $entry['context'] === "image_id={$entity->getId()}",
        ));
        $this->assertCount(1, $entries);
        $this->assertSame($mod->id, $entries[0]['user']['id']);
        $this->assertSame("image_id={$entity->getId()}", $entries[0]['context']);
    }

    public function testDeleteAction_FailedDelete_WritesNoAuditLogEntry(): void
    {
        $this->loginUserWithAccessLevel('failed-delete-mod-user', 'Failed Delete Mod', AccessLevel::MOD);

        $response = $this->simulateReviewPostRequest('/api/image/delete', '99999');

        $this->assertSame(HttpStatus::NOT_FOUND, $response->getStatus());
        $this->assertSame(
            [],
            array_filter(
                $this->auditLogService->getEntries(1, '')['entries'],
                static fn(array $entry): bool => $entry['actionType'] === AuditActionType::IMAGE_QUEUED_FOR_DELETION->value
                    && $entry['context'] === 'image_id=99999',
            ),
        );
    }

    public function testDeleteAction_UnknownId_ReturnsNotFound(): void
    {
        $this->loginUserWithAccessLevel('mod-user', 'Mod User', AccessLevel::MOD);

        $response = $this->simulateReviewPostRequest('/api/image/delete', '99999');

        $this->assertSame(HttpStatus::NOT_FOUND, $response->getStatus());
        $this->assertNull($response->getContent());
    }

    #[DataProvider('malformedIdProvider')]
    public function testDeleteAction_MalformedId_ReturnsBadRequest(?string $id): void
    {
        $this->loginUserWithAccessLevel('mod-user', 'Mod User', AccessLevel::MOD);

        $response = $this->simulateReviewPostRequest('/api/image/delete', $id);

        $this->assertSame(HttpStatus::BAD_REQUEST, $response->getStatus());
        $this->assertNull($response->getContent());
    }

    private function simulateQueueRequest(): Response
    {
        $request = new Request(
            url: new Url(protocol: 'https', host: 'localhost', path: '/api/review/queue'),
            method: HttpMethod::GET,
        );
        return $this->simulateRequest($request);
    }

    private function simulateReviewPostRequest(string $path, ?string $id): Response
    {
        $request = new Request(
            url: new Url(protocol: 'https', host: 'localhost', path: $path),
            method: HttpMethod::POST,
            getParams: $id === null ? null : ['id' => $id],
        );
        return $this->simulateRequest($request);
    }
}
