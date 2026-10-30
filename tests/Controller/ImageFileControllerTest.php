<?php
declare(strict_types=1);

namespace SmolCms\Test\Controller;

use PHPUnit\Framework\Attributes\DataProvider;
use SmolCms\Data\Business\Url;
use SmolCms\Data\Constant\AccessLevel;
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

class ImageFileControllerTest extends FunctionalTestCase
{
    use LoginWithAccessLevel;

    private const SAMPLE_INTERNAL_PREFIX = '/internal/img/sample/';
    private const DOWNLOAD_INTERNAL_PREFIX = '/internal/img/dl/';

    #[Autowire]
    private ImageEntityService $imageEntityService;

    #[Autowire]
    private TagService $tagService;

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

    public static function malformedNameProvider(): array
    {
        return [
            'missing name' => [null],
            'empty name' => [''],
            'traversal' => ['../private/img/sample/ab.webp'],
            'path separator' => ['ab/' . str_repeat('a', 62) . '.webp'],
            'non-hex name' => [str_repeat('z', 64) . '.webp'],
            'uppercase hex' => [strtoupper(str_repeat('a', 64)) . '.webp'],
            'wrong length' => ['abc.webp'],
            'unknown extension' => [str_repeat('a', 64) . '.php'],
        ];
    }

    public function testSampleAction_AnonymousAcceptedImage_ServesInternalRedirect(): void
    {
        $this->loginUser('anonymous-sample-owner-' . bin2hex(random_bytes(4)), 'Anonymous Sample Owner');
        $owner = $this->sessionService->getUserData();
        self::assertNotNull($owner);
        $sampleFilename = ImageTestUtils::randomChecksum() . '.webp';
        ImageTestUtils::createImageEntity(
            $this->imageEntityService,
            userId: $owner->id,
            status: ImageStatus::ACCEPTED,
            sampleFilename: $sampleFilename,
        );
        ImageTestUtils::writeImageFile(IMAGE_SAMPLE_DIR, $sampleFilename);
        $this->sessionService->destroySession();

        $response = $this->simulateSampleRequest($sampleFilename);

        $this->assertSame(HttpStatus::OK, $response->getStatus());
        $this->assertNull($response->getContent());
        $headers = $response->getHeaders();
        $expectedRedirect = self::SAMPLE_INTERNAL_PREFIX . substr($sampleFilename, 0, 2) . '/' . $sampleFilename;
        $this->assertSame($expectedRedirect, $headers[HttpHeader::X_ACCEL_REDIRECT->value]);
        $this->assertSame('image/webp', $headers[HttpHeader::CONTENT_TYPE->value]);
        $this->assertArrayNotHasKey(HttpHeader::CONTENT_DISPOSITION->value, $headers);
    }

    public function testDownloadAction_Anonymous_ReturnsUnauthorized(): void
    {
        $response = $this->simulateDownloadRequest(ImageTestUtils::randomChecksum() . '.webp');

        $this->assertSame(HttpStatus::UNAUTHORIZED, $response->getStatus());
        $this->assertNull($response->getContent());
    }

    #[DataProvider('malformedNameProvider')]
    public function testSampleAction_MalformedName_ReturnsBadRequest(?string $name): void
    {
        $this->loginUser('file-user', 'File User');

        $response = $this->simulateSampleRequest($name);

        $this->assertSame(HttpStatus::BAD_REQUEST, $response->getStatus());
        $this->assertNull($response->getContent());
    }

    #[DataProvider('malformedNameProvider')]
    public function testDownloadAction_MalformedName_ReturnsBadRequest(?string $name): void
    {
        $this->loginUser('file-user', 'File User');

        $response = $this->simulateDownloadRequest($name);

        $this->assertSame(HttpStatus::BAD_REQUEST, $response->getStatus());
        $this->assertNull($response->getContent());
    }

    public function testSampleAction_QueuedAcceptedImage_IsForbiddenAnonymouslyButServedToModerators(): void
    {
        $sampleFilename = ImageTestUtils::randomChecksum() . '.webp';
        ImageTestUtils::createImageEntity(
            $this->imageEntityService,
            userId: 1,
            status: ImageStatus::ACCEPTED,
            sampleFilename: $sampleFilename,
            deletedAt: new \DateTime('2026-10-20 09:00:00'),
        );
        ImageTestUtils::writeImageFile(IMAGE_SAMPLE_DIR, $sampleFilename);

        $anonymousResponse = $this->simulateSampleRequest($sampleFilename);
        $this->assertSame(HttpStatus::FORBIDDEN, $anonymousResponse->getStatus());

        $this->loginUserWithAccessLevel(
            'queued-sample-mod-' . bin2hex(random_bytes(4)),
            'Queued Sample Moderator',
            AccessLevel::MOD,
        );
        $moderatorResponse = $this->simulateSampleRequest($sampleFilename);
        $this->assertSame(HttpStatus::OK, $moderatorResponse->getStatus());
        $this->assertNull($moderatorResponse->getContent());
    }

    public function testDownloadAction_QueuedImage_IsForbiddenToRegularUsers(): void
    {
        $downloadFilename = ImageTestUtils::randomChecksum() . '.jpeg';
        ImageTestUtils::createImageEntity(
            $this->imageEntityService,
            userId: 1,
            status: ImageStatus::ACCEPTED,
            downloadFilename: $downloadFilename,
            deletedAt: new \DateTime('2026-10-20 09:00:00'),
        );
        ImageTestUtils::writeImageFile(IMAGE_DOWNLOAD_DIR, $downloadFilename);
        $this->loginUser('queued-download-user', 'Queued Download User');

        $response = $this->simulateDownloadRequest($downloadFilename);

        $this->assertSame(HttpStatus::FORBIDDEN, $response->getStatus());
        $this->assertNull($response->getContent());
    }

    public function testSampleAction_AcceptedImage_ServesInternalRedirectWithImageType(): void
    {
        $this->loginUser('file-user', 'File User');
        $sampleFilename = ImageTestUtils::randomChecksum() . '.webp';
        ImageTestUtils::createImageEntity(
            $this->imageEntityService,
            userId: 1,
            status: ImageStatus::ACCEPTED,
            sampleFilename: $sampleFilename,
        );
        ImageTestUtils::writeImageFile(IMAGE_SAMPLE_DIR, $sampleFilename);

        $response = $this->simulateSampleRequest($sampleFilename);

        $this->assertSame(HttpStatus::OK, $response->getStatus());
        $this->assertNull($response->getContent());
        $headers = $response->getHeaders();
        $expectedRedirect = self::SAMPLE_INTERNAL_PREFIX . substr($sampleFilename, 0, 2) . '/' . $sampleFilename;
        $this->assertSame($expectedRedirect, $headers[HttpHeader::X_ACCEL_REDIRECT->value]);
        $this->assertSame('image/webp', $headers[HttpHeader::CONTENT_TYPE->value]);
        $this->assertArrayNotHasKey(HttpHeader::CONTENT_DISPOSITION->value, $headers);
    }

    public function testDownloadAction_AcceptedImage_ServesInternalRedirectAsAttachment(): void
    {
        $this->loginUser('file-user', 'File User');
        $downloadFilename = ImageTestUtils::randomChecksum() . '.jpeg';
        ImageTestUtils::createImageEntity(
            $this->imageEntityService,
            userId: 1,
            status: ImageStatus::ACCEPTED,
            downloadFilename: $downloadFilename,
        );
        ImageTestUtils::writeImageFile(IMAGE_DOWNLOAD_DIR, $downloadFilename);

        $response = $this->simulateDownloadRequest($downloadFilename);

        $this->assertSame(HttpStatus::OK, $response->getStatus());
        $this->assertNull($response->getContent());
        $headers = $response->getHeaders();
        $expectedRedirect = self::DOWNLOAD_INTERNAL_PREFIX . substr($downloadFilename, 0, 2) . '/' . $downloadFilename;
        $this->assertSame($expectedRedirect, $headers[HttpHeader::X_ACCEL_REDIRECT->value]);
        $this->assertSame('image/jpeg', $headers[HttpHeader::CONTENT_TYPE->value]);
        $this->assertSame(
            "attachment; filename=\"$downloadFilename\"",
            $headers[HttpHeader::CONTENT_DISPOSITION->value]
        );
    }

    public function testSampleAction_UnknownName_ReturnsNotFound(): void
    {
        $this->loginUser('file-user', 'File User');

        $response = $this->simulateSampleRequest(ImageTestUtils::randomChecksum() . '.webp');

        $this->assertSame(HttpStatus::NOT_FOUND, $response->getStatus());
        $this->assertNull($response->getContent());
    }

    public function testDownloadAction_UnknownValidChecksum_ReturnsNotFound(): void
    {
        $this->loginUser('download-file-user', 'Download File User');

        $response = $this->simulateDownloadRequest(ImageTestUtils::randomChecksum() . '.webp');

        $this->assertSame(HttpStatus::NOT_FOUND, $response->getStatus());
        $this->assertNull($response->getContent());
    }

    public function testDownloadAction_MissingFile_ReturnsNotFound(): void
    {
        $this->loginUser('download-file-user', 'Download File User');
        $downloadFilename = ImageTestUtils::randomChecksum() . '.webp';
        ImageTestUtils::createImageEntity(
            $this->imageEntityService,
            userId: 1,
            status: ImageStatus::ACCEPTED,
            downloadFilename: $downloadFilename,
        );

        $response = $this->simulateDownloadRequest($downloadFilename);

        $this->assertSame(HttpStatus::NOT_FOUND, $response->getStatus());
        $this->assertNull($response->getContent());
    }

    public function testSampleAction_MissingFile_ReturnsNotFound(): void
    {
        $this->loginUser('file-user', 'File User');
        $sampleFilename = ImageTestUtils::randomChecksum() . '.webp';
        ImageTestUtils::createImageEntity(
            $this->imageEntityService,
            userId: 1,
            status: ImageStatus::ACCEPTED,
            sampleFilename: $sampleFilename,
        );

        $response = $this->simulateSampleRequest($sampleFilename);

        $this->assertSame(HttpStatus::NOT_FOUND, $response->getStatus());
        $this->assertNull($response->getContent());
    }

    public function testSampleAction_FileOutsideWhitelist_ReturnsNotFoundWithoutRedirect(): void
    {
        $sampleFilename = ImageTestUtils::randomChecksum() . '.webp';
        ImageTestUtils::createImageEntity(
            $this->imageEntityService,
            userId: 1,
            status: ImageStatus::ACCEPTED,
            sampleFilename: $sampleFilename,
        );
        $samplePath = ImageTestUtils::imageFilePath(IMAGE_SAMPLE_DIR, $sampleFilename);
        ImageTestUtils::writeImageFile(IMAGE_SAMPLE_DIR, $sampleFilename);
        self::assertTrue(unlink($samplePath));
        self::assertTrue(symlink('/etc/passwd', $samplePath));

        $response = $this->simulateSampleRequest($sampleFilename);

        self::assertSame(HttpStatus::NOT_FOUND, $response->getStatus());
        self::assertNull($response->getContent());
        self::assertArrayNotHasKey(HttpHeader::X_ACCEL_REDIRECT->value, $response->getHeaders());
    }

    public function testSampleAction_PendingReviewImage_NonUploaderForbiddenModeratorAllowed(): void
    {
        $this->loginUser('novice-user', 'Novice User');
        $viewer = $this->sessionService->getUserData();
        self::assertNotNull($viewer);
        $sampleFilename = ImageTestUtils::randomChecksum() . '.webp';
        ImageTestUtils::createImageEntity(
            $this->imageEntityService,
            userId: $viewer->id === 1 ? 2 : 1,
            status: ImageStatus::PENDING_REVIEW,
            sampleFilename: $sampleFilename,
        );
        ImageTestUtils::writeImageFile(IMAGE_SAMPLE_DIR, $sampleFilename);

        $noviceResponse = $this->simulateSampleRequest($sampleFilename);
        $this->assertSame(HttpStatus::FORBIDDEN, $noviceResponse->getStatus());

        // MOD+ reviewers may preview queued images.
        $this->loginUserWithAccessLevel('mod-user', 'Mod User', AccessLevel::MOD);
        $modResponse = $this->simulateSampleRequest($sampleFilename);
        $this->assertSame(HttpStatus::OK, $modResponse->getStatus());
    }

    public function testSampleAction_AnonymousPendingImage_ReturnsForbidden(): void
    {
        $this->loginUser('anonymous-pending-owner-' . bin2hex(random_bytes(4)), 'Anonymous Pending Owner');
        $owner = $this->sessionService->getUserData();
        self::assertNotNull($owner);
        $sampleFilename = ImageTestUtils::randomChecksum() . '.webp';
        ImageTestUtils::createImageEntity(
            $this->imageEntityService,
            userId: $owner->id,
            status: ImageStatus::PENDING_REVIEW,
            sampleFilename: $sampleFilename,
        );
        ImageTestUtils::writeImageFile(IMAGE_SAMPLE_DIR, $sampleFilename);
        $this->sessionService->destroySession();

        $response = $this->simulateSampleRequest($sampleFilename);

        $this->assertSame(HttpStatus::FORBIDDEN, $response->getStatus());
        $this->assertNull($response->getContent());
    }

    public function testDownloadAction_PendingReviewImage_NonUploaderForbiddenModeratorAllowed(): void
    {
        $this->loginUser('novice-user', 'Novice User');
        $viewer = $this->sessionService->getUserData();
        self::assertNotNull($viewer);
        $downloadFilename = ImageTestUtils::randomChecksum() . '.webp';
        ImageTestUtils::createImageEntity(
            $this->imageEntityService,
            userId: $viewer->id === 1 ? 2 : 1,
            status: ImageStatus::PENDING_REVIEW,
            downloadFilename: $downloadFilename,
        );
        ImageTestUtils::writeImageFile(IMAGE_DOWNLOAD_DIR, $downloadFilename);

        $noviceResponse = $this->simulateDownloadRequest($downloadFilename);
        $this->assertSame(HttpStatus::FORBIDDEN, $noviceResponse->getStatus());

        $this->loginUserWithAccessLevel('mod-user', 'Mod User', AccessLevel::MOD);
        $modResponse = $this->simulateDownloadRequest($downloadFilename);
        $this->assertSame(HttpStatus::OK, $modResponse->getStatus());
    }

    public function testSampleAction_PendingTagmeImageIsVisibleToRegularGameUsers(): void
    {
        $this->loginUser('tagme-file-user', 'Tagme File User');
        $viewer = $this->sessionService->getUserData();
        self::assertNotNull($viewer);
        $sampleFilename = ImageTestUtils::randomChecksum() . '.webp';
        $image = ImageTestUtils::createImageEntity(
            $this->imageEntityService,
            userId: $viewer->id === 1 ? 2 : 1,
            status: ImageStatus::PENDING_REVIEW,
            sampleFilename: $sampleFilename,
        );
        $this->tagService->attachTagToImage($image->getId(), 'tagme');
        ImageTestUtils::writeImageFile(IMAGE_SAMPLE_DIR, $sampleFilename);

        $response = $this->simulateSampleRequest($sampleFilename);

        self::assertSame(HttpStatus::OK, $response->getStatus());
    }

    public function testUploaderCanFetchOwnPendingReviewSampleAndDownload(): void
    {
        $this->loginUser('pending-file-owner-' . bin2hex(random_bytes(4)), 'Pending File Owner');
        $owner = $this->sessionService->getUserData();
        self::assertNotNull($owner);

        $sampleFilename = ImageTestUtils::randomChecksum() . '.webp';
        $downloadFilename = ImageTestUtils::randomChecksum() . '.jpeg';
        ImageTestUtils::createImageEntity(
            $this->imageEntityService,
            userId: $owner->id,
            status: ImageStatus::PENDING_REVIEW,
            sampleFilename: $sampleFilename,
            downloadFilename: $downloadFilename,
        );
        ImageTestUtils::writeImageFile(IMAGE_SAMPLE_DIR, $sampleFilename);
        ImageTestUtils::writeImageFile(IMAGE_DOWNLOAD_DIR, $downloadFilename);

        self::assertSame(HttpStatus::OK, $this->simulateSampleRequest($sampleFilename)->getStatus());
        self::assertSame(HttpStatus::OK, $this->simulateDownloadRequest($downloadFilename)->getStatus());
    }

    private function simulateSampleRequest(?string $name): Response
    {
        return $this->simulateFileRequest(
            '/api/image/sample',
            HttpMethod::GET,
            $name === null ? [] : ['name' => $name],
        );
    }

    private function simulateDownloadRequest(?string $name): Response
    {
        return $this->simulateFileRequest(
            '/api/image/download',
            HttpMethod::GET,
            $name === null ? [] : ['name' => $name],
        );
    }

    private function simulateFileRequest(string $path, HttpMethod $method, array $getParams): Response
    {
        $request = new Request(
            url: new Url(protocol: 'https', host: 'localhost', path: $path),
            method: $method,
            getParams: $getParams === [] ? null : $getParams,
        );
        return $this->simulateRequest($request);
    }
}
