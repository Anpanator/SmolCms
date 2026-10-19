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
use SmolCms\Data\Constant\ImageStatus;
use SmolCms\Data\Request\Request;
use SmolCms\Data\Response\Response;
use SmolCms\Service\Core\Session\SessionService;
use SmolCms\Service\DB\ImageEntityService;
use SmolCms\TestUtils\Attributes\Autowire;
use SmolCms\TestUtils\FunctionalTestCase;
use SmolCms\TestUtils\ImageTestUtils;

class ImageApiControllerTest extends FunctionalTestCase
{
    #[Autowire]
    private ImageEntityService $imageEntityService;

    #[Autowire]
    private SessionService $sessionService;

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
        ImageTestUtils::createImageEntity(
            $this->imageEntityService,
            userId: 1,
            status: ImageStatus::PENDING_REVIEW,
            uploadDate: new DateTime('2026-10-19 12:00:00'),
            thumbFilename: ImageTestUtils::randomChecksum() . '.avif',
        );
        ImageTestUtils::createImageEntity(
            $this->imageEntityService,
            userId: 1,
            status: ImageStatus::PENDING,
            uploadDate: new DateTime('2026-10-19 13:00:00'),
        );

        $response = $this->simulateListRequest();

        $this->assertSame(HttpStatus::OK, $response->getStatus());
        $this->assertSame(HttpContentType::JSON->value, $response->getHeaders()[HttpHeader::CONTENT_TYPE->value]);
        $body = json_decode((string)$response->getContent(), true, JSON_THROW_ON_ERROR);
        $this->assertSame(
            [
                'images' => [
                    ['id' => $newerAccepted->getId(), 'thumbSrc' => '/public/img/thumb/' . substr($newerAccepted->getThumbFilename(), 0, 2) . '/' . $newerAccepted->getThumbFilename()],
                    ['id' => $olderAccepted->getId(), 'thumbSrc' => '/public/img/thumb/' . substr($olderAccepted->getThumbFilename(), 0, 2) . '/' . $olderAccepted->getThumbFilename()],
                ],
            ],
            $body
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
                    'id' => $entity->getId(),
                    'uploadDate' => '2026-10-19 10:30:00',
                    'uploader' => 'Upload User',
                    'width' => 100,
                    'height' => 50,
                    'format' => 'jpeg',
                    'fileSize' => 1234,
                    'sampleSrc' => '/api/image/sample?name=' . $sampleFilename,
                    'downloadSrc' => '/api/image/download?name=' . $downloadFilename,
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

    #[DataProvider('malformedIdProvider')]
    public function testGetImageDetailAction_MalformedId_ReturnsBadRequest(?string $id): void
    {
        $this->loginUser('viewer-user', 'Viewer User');

        $response = $this->simulateDetailRequest($id);

        $this->assertSame(HttpStatus::BAD_REQUEST, $response->getStatus());
        $this->assertNull($response->getContent());
    }

    private function simulateListRequest(): Response
    {
        $request = new Request(
            url: new Url(protocol: 'https', host: 'localhost', path: '/api/images'),
            method: HttpMethod::GET,
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
