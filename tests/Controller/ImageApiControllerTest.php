<?php
declare(strict_types=1);

namespace SmolCms\Test\Controller;

use DateTime;
use PHPUnit\Framework\Attributes\DataProvider;
use SmolCms\Data\Business\Url;
use SmolCms\Data\Constant\AccessLevel;
use SmolCms\Data\Constant\HttpContentType;
use SmolCms\Data\Constant\HttpHeader;
use SmolCms\Data\Constant\HttpMethod;
use SmolCms\Data\Constant\HttpStatus;
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
                'perPage' => 15,
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
                'perPage' => 15,
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

    public function testGetImageDetailAction_AnonymousCanAccessAcceptedImage(): void
    {
        $this->loginUser('anonymous-image-detail-owner-' . bin2hex(random_bytes(4)), 'Anonymous Detail Owner');
        $owner = $this->sessionService->getUserData();
        self::assertNotNull($owner);
        $image = ImageTestUtils::createImageEntity(
            $this->imageEntityService,
            userId: $owner->id,
            status: ImageStatus::ACCEPTED,
        );
        $this->sessionService->destroySession();

        $response = $this->simulateDetailRequest((string)$image->getId());

        self::assertSame(HttpStatus::OK, $response->getStatus());
        $body = json_decode((string)$response->getContent(), true, JSON_THROW_ON_ERROR);
        self::assertSame($image->getId(), $body['image']['id']);
        self::assertSame('accepted', $body['image']['status']);
    }

    public function testGetImageDetailAction_AnonymousCannotAccessPendingImage(): void
    {
        $this->loginUser('anonymous-pending-detail-owner-' . bin2hex(random_bytes(4)), 'Anonymous Pending Detail Owner');
        $owner = $this->sessionService->getUserData();
        self::assertNotNull($owner);
        $image = ImageTestUtils::createImageEntity(
            $this->imageEntityService,
            userId: $owner->id,
            status: ImageStatus::PENDING_REVIEW,
        );
        $this->sessionService->destroySession();

        $response = $this->simulateDetailRequest((string)$image->getId());

        self::assertSame(HttpStatus::FORBIDDEN, $response->getStatus());
        self::assertNull($response->getContent());
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
                    'uploaderId' => $uploader->id,
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

    public function testGetImageDetailAction_PendingReviewImagesAreVisibleToUploaderAndModsButNotTagmeViewers(): void
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
        self::assertSame(HttpStatus::FORBIDDEN, $response->getStatus());

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

    public function testGetImageNeighborAction_AnonymousSkipsInvisibleImages(): void
    {
        $this->loginUser('neighbor-uploader-' . bin2hex(random_bytes(4)), 'Neighbor Uploader');
        $uploader = $this->sessionService->getUserData();
        self::assertNotNull($uploader);
        $acceptedFirst = ImageTestUtils::createImageEntity(
            $this->imageEntityService,
            userId: $uploader->id,
            status: ImageStatus::ACCEPTED,
        );
        $foreignPendingReview = ImageTestUtils::createImageEntity(
            $this->imageEntityService,
            userId: $uploader->id === 1 ? 2 : 1,
            status: ImageStatus::PENDING_REVIEW,
        );
        $acceptedSecond = ImageTestUtils::createImageEntity(
            $this->imageEntityService,
            userId: $uploader->id,
            status: ImageStatus::ACCEPTED,
        );
        $this->sessionService->destroySession();

        $nextResponse = $this->simulateNeighborRequest((string)$acceptedFirst->getId(), 'next');
        self::assertSame(HttpStatus::OK, $nextResponse->getStatus());
        self::assertSame(
            ['id' => $acceptedSecond->getId()],
            json_decode((string)$nextResponse->getContent(), true, JSON_THROW_ON_ERROR),
            'unapproved images of other users must be skipped',
        );

        $previousResponse = $this->simulateNeighborRequest((string)$acceptedSecond->getId(), 'previous');
        self::assertSame(HttpStatus::OK, $previousResponse->getStatus());
        self::assertSame(
            ['id' => $acceptedFirst->getId()],
            json_decode((string)$previousResponse->getContent(), true, JSON_THROW_ON_ERROR),
        );
    }

    public function testGetImageNeighborAction_RegularViewerIncludesOwnPendingReviewImages(): void
    {
        $this->loginUser('neighbor-owner-' . bin2hex(random_bytes(4)), 'Neighbor Owner');
        $owner = $this->sessionService->getUserData();
        self::assertNotNull($owner);
        $accepted = ImageTestUtils::createImageEntity(
            $this->imageEntityService,
            userId: $owner->id,
            status: ImageStatus::ACCEPTED,
        );
        $ownUnprocessed = ImageTestUtils::createImageEntity(
            $this->imageEntityService,
            userId: $owner->id,
            status: ImageStatus::PENDING,
        );
        $ownPendingReview = ImageTestUtils::createImageEntity(
            $this->imageEntityService,
            userId: $owner->id,
            status: ImageStatus::PENDING_REVIEW,
        );

        $nextResponse = $this->simulateNeighborRequest((string)$accepted->getId(), 'next');
        self::assertSame(HttpStatus::OK, $nextResponse->getStatus());
        self::assertSame(
            ['id' => $ownPendingReview->getId()],
            json_decode((string)$nextResponse->getContent(), true, JSON_THROW_ON_ERROR),
            'own pending-review images are visible, unprocessed ones are not',
        );

        $previousResponse = $this->simulateNeighborRequest((string)$ownPendingReview->getId(), 'previous');
        self::assertSame(HttpStatus::OK, $previousResponse->getStatus());
        self::assertSame(
            ['id' => $accepted->getId()],
            json_decode((string)$previousResponse->getContent(), true, JSON_THROW_ON_ERROR),
            'the unprocessed upload between the two images must be skipped',
        );
    }

    public function testGetImageNeighborAction_ModeratorCanNavigateThroughEveryStatus(): void
    {
        $this->loginUserWithAccessLevel(
            'neighbor-mod-' . bin2hex(random_bytes(4)),
            'Neighbor Moderator',
            AccessLevel::MOD,
        );
        $mod = $this->sessionService->getUserData();
        self::assertNotNull($mod);
        $accepted = ImageTestUtils::createImageEntity(
            $this->imageEntityService,
            userId: $mod->id === 1 ? 2 : 1,
            status: ImageStatus::ACCEPTED,
        );
        $rejected = ImageTestUtils::createImageEntity(
            $this->imageEntityService,
            userId: $mod->id === 1 ? 2 : 1,
            status: ImageStatus::REJECTED,
        );

        $nextResponse = $this->simulateNeighborRequest((string)$accepted->getId(), 'next');
        self::assertSame(HttpStatus::OK, $nextResponse->getStatus());
        self::assertSame(
            ['id' => $rejected->getId()],
            json_decode((string)$nextResponse->getContent(), true, JSON_THROW_ON_ERROR),
            'mods may open images of any status, so none are skipped',
        );

        $previousResponse = $this->simulateNeighborRequest((string)$rejected->getId(), 'previous');
        self::assertSame(HttpStatus::OK, $previousResponse->getStatus());
        self::assertSame(
            ['id' => $accepted->getId()],
            json_decode((string)$previousResponse->getContent(), true, JSON_THROW_ON_ERROR),
        );
    }

    public function testGetImageNeighborAction_UnknownId_ReturnsNotFound(): void
    {
        $response = $this->simulateNeighborRequest('99999', 'next');

        $this->assertSame(HttpStatus::NOT_FOUND, $response->getStatus());
        $this->assertNull($response->getContent());
    }

    public function testGetImageNeighborAction_InaccessibleId_ReturnsForbidden(): void
    {
        $this->loginUser('neighbor-foreign-owner-' . bin2hex(random_bytes(4)), 'Neighbor Foreign Owner');
        $owner = $this->sessionService->getUserData();
        self::assertNotNull($owner);
        $foreignPendingReview = ImageTestUtils::createImageEntity(
            $this->imageEntityService,
            userId: $owner->id,
            status: ImageStatus::PENDING_REVIEW,
        );
        $this->sessionService->destroySession();

        $response = $this->simulateNeighborRequest((string)$foreignPendingReview->getId(), 'next');

        $this->assertSame(HttpStatus::FORBIDDEN, $response->getStatus());
        $this->assertNull($response->getContent());
    }

    #[DataProvider('malformedIdProvider')]
    public function testGetImageNeighborAction_MalformedId_ReturnsBadRequest(?string $id): void
    {
        $response = $this->simulateNeighborRequest($id, 'next');

        $this->assertSame(HttpStatus::BAD_REQUEST, $response->getStatus());
        $this->assertNull($response->getContent());
    }

    public static function invalidDirectionProvider(): array
    {
        return [
            'missing direction' => [null],
            'empty direction' => [''],
            'unknown direction' => ['sideways'],
            'non-string direction' => [['next']],
        ];
    }

    #[DataProvider('invalidDirectionProvider')]
    public function testGetImageNeighborAction_MalformedDirection_ReturnsBadRequest(mixed $direction): void
    {
        $response = $this->simulateNeighborRequest('1', $direction);

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

    private function simulateNeighborRequest(?string $id, mixed $direction): Response
    {
        $params = [];
        if ($id !== null) {
            $params['id'] = $id;
        }
        if ($direction !== null) {
            $params['direction'] = $direction;
        }
        $request = new Request(
            url: new Url(protocol: 'https', host: 'localhost', path: '/api/image/neighbor'),
            method: HttpMethod::GET,
            getParams: $params === [] ? null : $params,
        );
        return $this->simulateRequest($request);
    }
}
