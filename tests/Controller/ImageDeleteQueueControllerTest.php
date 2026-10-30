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

class ImageDeleteQueueControllerTest extends FunctionalTestCase
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

    public function testQueueAction_ReturnsOnlyQueuedImagesNewestFirst(): void
    {
        $this->loginUser('queue-uploader', 'Queue Uploader');
        $uploader = $this->sessionService->getUserData();
        $this->assertNotNull($uploader);
        $uploaderId = $uploader->id;

        $olderThumbFilename = ImageTestUtils::randomChecksum() . '.avif';
        $older = ImageTestUtils::createImageEntity(
            $this->imageEntityService,
            userId: $uploaderId,
            status: ImageStatus::ACCEPTED,
            uploadDate: new DateTime('2026-10-19 09:00:00'),
            thumbFilename: $olderThumbFilename,
            sampleFilename: ImageTestUtils::randomChecksum() . '.webp',
            deletedAt: new DateTime('2026-10-20 09:00:00'),
        );
        ImageTestUtils::writeImageFile(IMAGE_THUMB_DIR, $olderThumbFilename);
        $newer = ImageTestUtils::createImageEntity(
            $this->imageEntityService,
            userId: $uploaderId,
            status: ImageStatus::PENDING_REVIEW,
            uploadDate: new DateTime('2026-10-19 11:00:00'),
            deletedAt: new DateTime('2026-10-21 11:00:00'),
        );
        ImageTestUtils::createImageEntity(
            $this->imageEntityService,
            userId: $uploaderId,
            status: ImageStatus::ACCEPTED,
            uploadDate: new DateTime('2026-10-19 10:00:00'),
        );

        $this->loginUserWithAccessLevel('queue-mod-user', 'Queue Mod User', AccessLevel::MOD);

        $response = $this->simulateQueueRequest();

        $this->assertSame(HttpStatus::OK, $response->getStatus());
        $this->assertSame(HttpContentType::JSON->value, $response->getHeaders()[HttpHeader::CONTENT_TYPE->value]);
        $this->assertSame(
            [
                'images' => [
                    [
                        'id' => $newer->getId(),
                        'thumbSrc' => null,
                        'sampleSrc' => null,
                        'uploadDate' => '2026-10-19 11:00:00',
                        'uploaderId' => $uploaderId,
                        'uploader' => 'Queue Uploader',
                    ],
                    [
                        'id' => $older->getId(),
                        'thumbSrc' => '/public/img/thumb/' . substr((string)$older->getThumbFilename(), 0, 2) . '/' . $older->getThumbFilename(),
                        'sampleSrc' => '/api/image/sample?name=' . $older->getSampleFilename(),
                        'uploadDate' => '2026-10-19 09:00:00',
                        'uploaderId' => $uploaderId,
                        'uploader' => 'Queue Uploader',
                    ],
                ],
            ],
            json_decode((string)$response->getContent(), true, JSON_THROW_ON_ERROR)
        );
    }

    public function testQueueAction_ExcludesQueuedImagesFromTheReviewQueue(): void
    {
        $this->loginUserWithAccessLevel('review-queue-mod', 'Review Queue Mod', AccessLevel::MOD);
        $visible = ImageTestUtils::createImageEntity(
            $this->imageEntityService,
            userId: 99999999,
            status: ImageStatus::PENDING_REVIEW,
        );
        ImageTestUtils::createImageEntity(
            $this->imageEntityService,
            userId: 99999999,
            status: ImageStatus::PENDING_REVIEW,
            deletedAt: new DateTime('2026-10-20 09:00:00'),
        );

        $request = new Request(
            url: new Url(protocol: 'https', host: 'localhost', path: '/api/review/queue'),
            method: HttpMethod::GET,
        );
        $response = $this->simulateRequest($request);

        $this->assertSame(HttpStatus::OK, $response->getStatus());
        $this->assertSame(
            ['images' => [[
                'id' => $visible->getId(),
                'thumbSrc' => null,
                'sampleSrc' => null,
                'uploadDate' => $visible->getUploadDate()->format('Y-m-d H:i:s'),
                'uploaderId' => null,
                'uploader' => null,
            ]]],
            json_decode((string)$response->getContent(), true, JSON_THROW_ON_ERROR)
        );
    }

    public function testRestoreAction_Anonymous_ReturnsUnauthorized(): void
    {
        $response = $this->simulateRestoreRequest('1');

        $this->assertSame(HttpStatus::UNAUTHORIZED, $response->getStatus());
        $this->assertNull($response->getContent());
    }

    public function testRestoreAction_Novice_ReturnsForbidden(): void
    {
        $this->loginUser('novice-user', 'Novice User');

        $response = $this->simulateRestoreRequest('1');

        $this->assertSame(HttpStatus::FORBIDDEN, $response->getStatus());
        $this->assertNull($response->getContent());
    }

    public function testRestoreAction_RestoresQueuedImageAndWritesAuditLog(): void
    {
        $this->loginUserWithAccessLevel('restore-mod-user', 'Restore Mod User', AccessLevel::MOD);
        $mod = $this->sessionService->getUserData();
        $this->assertNotNull($mod);
        $entity = ImageTestUtils::createImageEntity(
            $this->imageEntityService,
            userId: 1,
            status: ImageStatus::ACCEPTED,
            deletedAt: new DateTime('2026-10-20 09:00:00'),
        );

        $response = $this->simulateRestoreRequest((string)$entity->getId());

        $this->assertSame(HttpStatus::NO_CONTENT, $response->getStatus());
        $this->assertNull($response->getContent());
        $storedEntity = $this->imageEntityService->findOneById($entity->getId());
        $this->assertNotNull($storedEntity);
        $this->assertFalse($storedEntity->isDeleted());

        $entries = array_values(array_filter(
            $this->auditLogService->getEntries(1, '')['entries'],
            static fn(array $entry): bool => $entry['actionType'] === AuditActionType::IMAGE_RESTORED->value
                && $entry['context'] === "image_id={$entity->getId()}",
        ));
        $this->assertCount(1, $entries);
        $this->assertSame($mod->id, $entries[0]['user']['id']);
    }

    public function testRestoreAction_NonQueuedImage_IsIdempotent(): void
    {
        $this->loginUserWithAccessLevel('restore-mod-user', 'Restore Mod User', AccessLevel::MOD);
        $entity = ImageTestUtils::createImageEntity(
            $this->imageEntityService,
            userId: 1,
            status: ImageStatus::ACCEPTED,
        );

        $response = $this->simulateRestoreRequest((string)$entity->getId());

        $this->assertSame(HttpStatus::NO_CONTENT, $response->getStatus());
        $storedEntity = $this->imageEntityService->findOneById($entity->getId());
        $this->assertNotNull($storedEntity);
        $this->assertFalse($storedEntity->isDeleted());
    }

    public function testRestoreAction_UnknownId_ReturnsNotFound(): void
    {
        $this->loginUserWithAccessLevel('restore-mod-user', 'Restore Mod User', AccessLevel::MOD);

        $response = $this->simulateRestoreRequest('99999');

        $this->assertSame(HttpStatus::NOT_FOUND, $response->getStatus());
        $this->assertNull($response->getContent());
    }

    #[DataProvider('malformedIdProvider')]
    public function testRestoreAction_MalformedId_ReturnsBadRequest(?string $id): void
    {
        $this->loginUserWithAccessLevel('restore-mod-user', 'Restore Mod User', AccessLevel::MOD);

        $response = $this->simulateRestoreRequest($id);

        $this->assertSame(HttpStatus::BAD_REQUEST, $response->getStatus());
        $this->assertNull($response->getContent());
    }

    public function testDeleteAction_Anonymous_ReturnsUnauthorized(): void
    {
        $response = $this->simulateDeleteRequest('1');

        $this->assertSame(HttpStatus::UNAUTHORIZED, $response->getStatus());
        $this->assertNull($response->getContent());
    }

    public function testDeleteAction_Novice_ReturnsForbidden(): void
    {
        $this->loginUser('novice-user', 'Novice User');

        $response = $this->simulateDeleteRequest('1');

        $this->assertSame(HttpStatus::FORBIDDEN, $response->getStatus());
        $this->assertNull($response->getContent());
    }

    public function testDeleteAction_QueuedImage_DeletesRowAndAllFiles(): void
    {
        $this->loginUserWithAccessLevel('purge-mod-user', 'Purge Mod User', AccessLevel::MOD);
        $checksum = ImageTestUtils::randomChecksum();
        $entity = ImageTestUtils::createImageEntity(
            $this->imageEntityService,
            userId: 1,
            status: ImageStatus::ACCEPTED,
            checksum: $checksum,
            thumbFilename: ImageTestUtils::randomChecksum() . '.avif',
            sampleFilename: ImageTestUtils::randomChecksum() . '.webp',
            downloadFilename: ImageTestUtils::randomChecksum() . '.jpeg',
            deletedAt: new DateTime('2026-10-20 09:00:00'),
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

        $response = $this->simulateDeleteRequest((string)$entity->getId());

        $this->assertSame(HttpStatus::NO_CONTENT, $response->getStatus());
        $this->assertNull($response->getContent());
        $this->assertNull($this->imageEntityService->findOneById($entity->getId()));
        $this->assertFileDoesNotExist($originalPath);
        $this->assertFileDoesNotExist($thumbPath);
        $this->assertFileDoesNotExist($samplePath);
        $this->assertFileDoesNotExist($downloadPath);
    }

    public function testDeleteAction_QueuedImage_WritesImageDeletedAuditLogEntry(): void
    {
        $this->loginUserWithAccessLevel('purge-audit-mod', 'Purge Audit Mod', AccessLevel::MOD);
        $mod = $this->sessionService->getUserData();
        $this->assertNotNull($mod);
        $entity = ImageTestUtils::createImageEntity(
            $this->imageEntityService,
            userId: 1,
            status: ImageStatus::ACCEPTED,
            deletedAt: new DateTime('2026-10-20 09:00:00'),
        );

        $response = $this->simulateDeleteRequest((string)$entity->getId());

        $this->assertSame(HttpStatus::NO_CONTENT, $response->getStatus());
        $entries = array_values(array_filter(
            $this->auditLogService->getEntries(1, '')['entries'],
            static fn(array $entry): bool => $entry['actionType'] === AuditActionType::IMAGE_DELETED->value
                && $entry['context'] === "image_id={$entity->getId()}",
        ));
        $this->assertCount(1, $entries);
        $this->assertSame($mod->id, $entries[0]['user']['id']);
    }

    public function testDeleteAction_NonQueuedImage_ReturnsNotFoundAndKeepsImage(): void
    {
        $this->loginUserWithAccessLevel('purge-mod-user', 'Purge Mod User', AccessLevel::MOD);
        $entity = ImageTestUtils::createImageEntity(
            $this->imageEntityService,
            userId: 1,
            status: ImageStatus::ACCEPTED,
        );

        $response = $this->simulateDeleteRequest((string)$entity->getId());

        $this->assertSame(HttpStatus::NOT_FOUND, $response->getStatus());
        $this->assertNotNull($this->imageEntityService->findOneById($entity->getId()));
        $this->assertSame(
            [],
            array_filter(
                $this->auditLogService->getEntries(1, '')['entries'],
                static fn(array $entry): bool => $entry['actionType'] === AuditActionType::IMAGE_DELETED->value
                    && $entry['context'] === "image_id={$entity->getId()}",
            ),
        );
    }

    public function testDeleteAction_UnknownId_ReturnsNotFound(): void
    {
        $this->loginUserWithAccessLevel('purge-mod-user', 'Purge Mod User', AccessLevel::MOD);

        $response = $this->simulateDeleteRequest('99999');

        $this->assertSame(HttpStatus::NOT_FOUND, $response->getStatus());
        $this->assertNull($response->getContent());
    }

    #[DataProvider('malformedIdProvider')]
    public function testDeleteAction_MalformedId_ReturnsBadRequest(?string $id): void
    {
        $this->loginUserWithAccessLevel('purge-mod-user', 'Purge Mod User', AccessLevel::MOD);

        $response = $this->simulateDeleteRequest($id);

        $this->assertSame(HttpStatus::BAD_REQUEST, $response->getStatus());
        $this->assertNull($response->getContent());
    }

    private function simulateQueueRequest(): Response
    {
        return $this->simulateRequest(new Request(
            url: new Url(protocol: 'https', host: 'localhost', path: '/api/delete-queue'),
            method: HttpMethod::GET,
        ));
    }

    private function simulateRestoreRequest(?string $id): Response
    {
        return $this->simulatePostRequest('/api/delete-queue/restore', $id);
    }

    private function simulateDeleteRequest(?string $id): Response
    {
        return $this->simulatePostRequest('/api/delete-queue/delete', $id);
    }

    private function simulatePostRequest(string $path, ?string $id): Response
    {
        return $this->simulateRequest(new Request(
            url: new Url(protocol: 'https', host: 'localhost', path: $path),
            method: HttpMethod::POST,
            getParams: $id === null ? null : ['id' => $id],
        ));
    }
}
