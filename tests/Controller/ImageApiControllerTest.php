<?php
declare(strict_types=1);

namespace SmolCms\Test\Controller;

use DateTime;
use PHPUnit\Framework\Attributes\DataProvider;
use SmolCms\Data\Business\Url;
use SmolCms\Data\Constant\HttpContentType;
use SmolCms\Data\Constant\HttpHeader;
use SmolCms\Data\Constant\HttpMethod;
use SmolCms\Data\Constant\HttpStatus;
use SmolCms\Data\Constant\AccessLevel;
use SmolCms\Data\Constant\ImageStatus;
use SmolCms\Data\Request\Request;
use SmolCms\Data\Response\Response;
use SmolCms\Service\Core\Session\SessionService;
use SmolCms\Service\DB\ImageEntityService;
use SmolCms\Service\Tag\TagService;
use SmolCms\TestUtils\Attributes\Autowire;
use SmolCms\TestUtils\FunctionalTestCase;
use SmolCms\TestUtils\ImageTestUtils;
use SmolCms\TestUtils\LoginWithAccessLevel;

class ImageApiControllerTest extends FunctionalTestCase
{
    use LoginWithAccessLevel;

    #[Autowire]
    private ImageEntityService $imageEntityService;

    #[Autowire]
    private SessionService $sessionService;

    #[Autowire]
    private TagService $tagService;

    protected function setUp(): void
    {
        parent::setUp();
        ImageTestUtils::cleanImageDirs();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
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

    public function testGetImageListAction_PublicAndListsOnlyAcceptedImagesNewestFirst(): void
    {
        $markerTag = 'image-list-marker-' . bin2hex(random_bytes(5));
        $this->tagService->createTag($markerTag);
        $olderAccepted = ImageTestUtils::createImageEntity(
            $this->imageEntityService,
            userId: 1,
            status: ImageStatus::ACCEPTED,
            uploadDate: new DateTime('2026-10-19 09:00:00'),
            thumbFilename: ImageTestUtils::randomChecksum() . '.avif',
        );
        $newerAccepted = ImageTestUtils::createImageEntity(
            $this->imageEntityService,
            userId: 1,
            status: ImageStatus::ACCEPTED,
            uploadDate: new DateTime('2026-10-19 11:00:00'),
            thumbFilename: ImageTestUtils::randomChecksum() . '.avif',
        );
        $this->tagService->attachTagToImage($olderAccepted->getId(), $markerTag);
        $this->tagService->attachTagToImage($newerAccepted->getId(), $markerTag);
        $pendingReview = ImageTestUtils::createImageEntity(
            $this->imageEntityService,
            userId: 1,
            status: ImageStatus::PENDING_REVIEW,
            uploadDate: new DateTime('2026-10-19 12:00:00'),
            thumbFilename: ImageTestUtils::randomChecksum() . '.avif',
        );
        $pending = ImageTestUtils::createImageEntity(
            $this->imageEntityService,
            userId: 1,
            status: ImageStatus::PENDING,
            uploadDate: new DateTime('2026-10-19 13:00:00'),
        );
        $this->tagService->attachTagToImage($pendingReview->getId(), $markerTag);
        $this->tagService->attachTagToImage($pending->getId(), $markerTag);

        $response = $this->simulateListRequest(['tags' => $markerTag]);

        $this->assertSame(HttpStatus::OK, $response->getStatus());
        $this->assertSame(HttpContentType::JSON->value, $response->getHeaders()[HttpHeader::CONTENT_TYPE->value]);
        $body = json_decode((string)$response->getContent(), true, JSON_THROW_ON_ERROR);
        $this->assertSame(
            [
                'images' => [
                    ['id' => $newerAccepted->getId(), 'thumbSrc' => '/public/img/thumb/' . substr($newerAccepted->getThumbFilename(), 0, 2) . '/' . $newerAccepted->getThumbFilename(), 'status' => 'accepted'],
                    ['id' => $olderAccepted->getId(), 'thumbSrc' => '/public/img/thumb/' . substr($olderAccepted->getThumbFilename(), 0, 2) . '/' . $olderAccepted->getThumbFilename(), 'status' => 'accepted'],
                ],
                'page' => 1,
                'perPage' => 25,
                'total' => 2,
                'tags' => [['name' => $markerTag, 'imageCount' => 2]],
            ],
            $body
        );
    }

    public function testGetImageListAction_LoggedInUserCanAccessPublicList(): void
    {
        $this->loginUser('image-list-viewer-' . bin2hex(random_bytes(4)), 'Image List Viewer');
        $user = $this->sessionService->getUserData();
        self::assertNotNull($user);

        $markerTag = 'logged-in-image-list-' . bin2hex(random_bytes(5));
        $this->tagService->createTag($markerTag);
        $thumbFilename = ImageTestUtils::randomChecksum() . '.avif';
        $image = ImageTestUtils::createImageEntity(
            $this->imageEntityService,
            userId: $user->id,
            status: ImageStatus::ACCEPTED,
            thumbFilename: $thumbFilename,
        );
        $this->tagService->attachTagToImage($image->getId(), $markerTag);

        $response = $this->simulateListRequest(['tags' => $markerTag]);

        self::assertSame(HttpStatus::OK, $response->getStatus());
        self::assertSame(
            [
                'images' => [[
                    'id' => $image->getId(),
                    'thumbSrc' => '/public/img/thumb/' . substr($thumbFilename, 0, 2) . '/' . $thumbFilename,
                    'status' => 'accepted',
                ]],
                'page' => 1,
                'perPage' => 25,
                'total' => 1,
                'tags' => [['name' => $markerTag, 'imageCount' => 1]],
            ],
            json_decode((string)$response->getContent(), true, JSON_THROW_ON_ERROR),
        );
    }

    public function testGetImageListAction_RegularUserSeesOwnPendingReviewImagesOnly(): void
    {
        $this->loginUser('image-list-owner-' . bin2hex(random_bytes(4)), 'Image List Owner');
        $user = $this->sessionService->getUserData();
        self::assertNotNull($user);
        $otherUserId = $user->id === 1 ? 2 : 1;
        $markerTag = 'image-list-owner-' . bin2hex(random_bytes(5));
        $this->tagService->createTag($markerTag);

        $accepted = ImageTestUtils::createImageEntity(
            $this->imageEntityService,
            userId: $otherUserId,
            status: ImageStatus::ACCEPTED,
            uploadDate: new DateTime('2026-10-19 10:00:00'),
        );
        $ownPending = ImageTestUtils::createImageEntity(
            $this->imageEntityService,
            userId: $user->id,
            status: ImageStatus::PENDING_REVIEW,
            uploadDate: new DateTime('2026-10-19 12:00:00'),
        );
        $otherPending = ImageTestUtils::createImageEntity(
            $this->imageEntityService,
            userId: $otherUserId,
            status: ImageStatus::PENDING_REVIEW,
            uploadDate: new DateTime('2026-10-19 13:00:00'),
        );
        foreach ([$accepted, $ownPending, $otherPending] as $image) {
            $this->tagService->attachTagToImage($image->getId(), $markerTag);
        }

        $response = $this->simulateListRequest(['tags' => $markerTag]);

        self::assertSame(HttpStatus::OK, $response->getStatus());
        $body = json_decode((string)$response->getContent(), true, JSON_THROW_ON_ERROR);
        self::assertSame(2, $body['total']);
        self::assertSame(
            [
                ['id' => $ownPending->getId(), 'thumbSrc' => null, 'status' => 'pending_review'],
                ['id' => $accepted->getId(), 'thumbSrc' => null, 'status' => 'accepted'],
            ],
            $body['images'],
        );
    }

    public function testGetImageListAction_ModeratorSeesAllPendingReviewImages(): void
    {
        $this->loginUserWithAccessLevel(
            'image-list-mod-' . bin2hex(random_bytes(4)),
            'Image List Moderator',
            AccessLevel::MOD,
        );
        $markerTag = 'image-list-mod-' . bin2hex(random_bytes(5));
        $this->tagService->createTag($markerTag);
        $olderPending = ImageTestUtils::createImageEntity(
            $this->imageEntityService,
            userId: 1,
            status: ImageStatus::PENDING_REVIEW,
            uploadDate: new DateTime('2026-10-19 10:00:00'),
        );
        $newerPending = ImageTestUtils::createImageEntity(
            $this->imageEntityService,
            userId: 2,
            status: ImageStatus::PENDING_REVIEW,
            uploadDate: new DateTime('2026-10-19 11:00:00'),
        );
        foreach ([$olderPending, $newerPending] as $image) {
            $this->tagService->attachTagToImage($image->getId(), $markerTag);
        }

        $response = $this->simulateListRequest(['tags' => $markerTag]);

        self::assertSame(HttpStatus::OK, $response->getStatus());
        $body = json_decode((string)$response->getContent(), true, JSON_THROW_ON_ERROR);
        self::assertSame(2, $body['total']);
        self::assertSame(
            [
                ['id' => $newerPending->getId(), 'thumbSrc' => null, 'status' => 'pending_review'],
                ['id' => $olderPending->getId(), 'thumbSrc' => null, 'status' => 'pending_review'],
            ],
            $body['images'],
        );
    }

    public function testGetImageDetailAction_RequiresAuthentication(): void
    {
        ImageTestUtils::createImageEntity($this->imageEntityService, userId: 1);

        $response = $this->simulateDetailRequest('1');

        $this->assertSame(HttpStatus::UNAUTHORIZED, $response->getStatus());
        $this->assertNull($response->getContent());
    }

    public function testGetImageDetailAction_ReturnsDetailWithUploaderDisplayName(): void
    {
        // The uploader is a different user than the requesting viewer.
        $this->loginUser('uploader-user', 'Upload User');
        $uploader = $this->sessionService->getUserData();
        $this->assertNotNull($uploader);
        $uploadDate = new DateTime('2026-10-19 10:30:00');
        $thumbFilename = ImageTestUtils::randomChecksum() . '.avif';
        $sampleFilename = ImageTestUtils::randomChecksum() . '.webp';
        $downloadFilename = ImageTestUtils::randomChecksum() . '.jpeg';
        $entity = ImageTestUtils::createImageEntity(
            $this->imageEntityService,
            userId: $uploader->id,
            status: ImageStatus::ACCEPTED,
            uploadDate: $uploadDate,
            thumbFilename: $thumbFilename,
            sampleFilename: $sampleFilename,
            downloadFilename: $downloadFilename,
        );

        $this->loginUser('viewer-user', 'Viewer User');
        $response = $this->simulateDetailRequest((string)$entity->getId());

        $this->assertSame(HttpStatus::OK, $response->getStatus());
        $this->assertSame(HttpContentType::JSON->value, $response->getHeaders()[HttpHeader::CONTENT_TYPE->value]);
        $this->assertSame(
            [
                'image' => [
                    'status' => 'accepted',
                    'id' => $entity->getId(),
                    'uploadDate' => '2026-10-19 10:30:00',
                    'uploader' => 'Upload User',
                    'width' => 100,
                    'height' => 50,
                    'format' => 'jpeg',
                    'fileSize' => 1234,
                    'sampleSrc' => '/api/image/sample?name=' . $sampleFilename,
                    'downloadSrc' => '/api/image/download?name=' . $downloadFilename,
                    'tags' => [],
                ],
            ],
            json_decode((string)$response->getContent(), true, JSON_THROW_ON_ERROR)
        );
    }

    public function testGetImageDetailAction_UnknownId_ReturnsNotFound(): void
    {
        $this->loginUser('viewer-user', 'Viewer User');

        $response = $this->simulateDetailRequest('99999');

        $this->assertSame(HttpStatus::NOT_FOUND, $response->getStatus());
        $this->assertNull($response->getContent());
    }

    public function testGetImageDetailAction_PendingReviewImagesAreVisibleToUploaderTagmeViewersAndMods(): void
    {
        $this->loginUser('image-detail-queue-' . bin2hex(random_bytes(4)), 'Queue Viewer');
        $viewer = $this->sessionService->getUserData();
        self::assertNotNull($viewer);
        $otherUserId = $viewer->id === 1 ? 2 : 1;
        $foreignPending = ImageTestUtils::createImageEntity(
            $this->imageEntityService,
            userId: $otherUserId,
            status: ImageStatus::PENDING_REVIEW,
        );
        $ownPending = ImageTestUtils::createImageEntity(
            $this->imageEntityService,
            userId: $viewer->id,
            status: ImageStatus::PENDING_REVIEW,
        );

        self::assertSame(HttpStatus::FORBIDDEN, $this->simulateDetailRequest((string)$foreignPending->getId())->getStatus());
        $ownResponse = $this->simulateDetailRequest((string)$ownPending->getId());
        self::assertSame(HttpStatus::OK, $ownResponse->getStatus());
        self::assertSame('pending_review', json_decode((string)$ownResponse->getContent(), true, JSON_THROW_ON_ERROR)['image']['status']);

        $this->tagService->attachTagToImage($foreignPending->getId(), 'tagme');
        $response = $this->simulateDetailRequest((string)$foreignPending->getId());
        self::assertSame(HttpStatus::OK, $response->getStatus());
        self::assertSame($foreignPending->getId(), json_decode((string)$response->getContent(), true, JSON_THROW_ON_ERROR)['image']['id']);

        $this->loginUserWithAccessLevel(
            'image-detail-mod-' . bin2hex(random_bytes(4)),
            'Queue Moderator',
            AccessLevel::MOD,
        );
        $hiddenPending = ImageTestUtils::createImageEntity(
            $this->imageEntityService,
            userId: $otherUserId,
            status: ImageStatus::PENDING_REVIEW,
        );
        self::assertSame(HttpStatus::OK, $this->simulateDetailRequest((string)$hiddenPending->getId())->getStatus());
    }

    #[DataProvider('malformedIdProvider')]
    public function testGetImageDetailAction_MalformedId_ReturnsBadRequest(?string $id): void
    {
        $this->loginUser('viewer-user', 'Viewer User');

        $response = $this->simulateDetailRequest($id);

        $this->assertSame(HttpStatus::BAD_REQUEST, $response->getStatus());
        $this->assertNull($response->getContent());
    }

    private function simulateListRequest(?array $params = null): Response
    {
        $request = new Request(
            url: new Url(protocol: 'https', host: 'localhost', path: '/api/images'),
            method: HttpMethod::GET,
            getParams: $params,
        );
        return $this->simulateRequest($request);
    }

    private function simulateDetailRequest(?string $id): Response
    {
        $request = new Request(
            url: new Url(protocol: 'https', host: 'localhost', path: '/api/image/detail'),
            method: HttpMethod::GET,
            getParams: $id === null ? null : ['id' => $id],
        );
        return $this->simulateRequest($request);
    }
}
