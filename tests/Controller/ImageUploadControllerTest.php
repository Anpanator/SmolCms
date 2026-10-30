<?php
declare(strict_types=1);

namespace SmolCms\Test\Controller;

use PDO;
use ReflectionProperty;
use SmolCms\Data\Business\Service;
use SmolCms\Data\Business\Url;
use SmolCms\Data\Constant\AccessLevel;
use SmolCms\Data\Constant\AuditActionType;
use SmolCms\Data\Constant\HttpContentType;
use SmolCms\Data\Constant\HttpHeader;
use SmolCms\Data\Constant\HttpMethod;
use SmolCms\Data\Constant\HttpStatus;
use SmolCms\Data\Constant\ImageStatus;
use SmolCms\Data\Request\ImageUploadRequest;
use SmolCms\Data\Request\Request;
use SmolCms\Service\Core\Session\SessionHandler;
use SmolCms\Service\Core\Session\SessionService;
use SmolCms\Service\Core\Startup\ResumeSessionStartupAction;
use SmolCms\Service\DB\ApiKeyService;
use SmolCms\Service\DB\AuditLogService;
use SmolCms\Service\DB\ImageEntityService;
use SmolCms\Service\Image\ImageTypeDetectionFacade;
use SmolCms\Service\Tag\TagService;
use SmolCms\Service\Validation\ImageUploadValidator;
use SmolCms\TestUtils\Attributes\Autowire;
use SmolCms\TestUtils\FunctionalTestCase;
use SmolCms\TestUtils\LoginWithAccessLevel;

readonly class TestImageUploadValidator extends ImageUploadValidator
{
    public function getStatus(Request|ImageUploadRequest $request): HttpStatus
    {
        return HttpStatus::OK;
    }
}

class ImageUploadControllerTest extends FunctionalTestCase
{
    use LoginWithAccessLevel;

    private Url $uploadUrl;

    #[Autowire]
    private ImageEntityService $imageEntityService;

    #[Autowire]
    private TagService $tagService;

    #[Autowire]
    private ApiKeyService $apiKeyService;

    #[Autowire]
    private AuditLogService $auditLogService;

    #[Autowire]
    private SessionService $sessionService;

    #[Autowire]
    private SessionHandler $sessionHandler;

    #[Autowire]
    private PDO $pdo;

    /** @var array<string, mixed> */
    private array $originalCookie;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::registerTestServices(
            new Service(
                identifier: ImageUploadValidator::class,
                class: TestImageUploadValidator::class,
                parameters: [ImageTypeDetectionFacade::class]
            )
        );
    }

    protected function setUp(): void
    {
        $this->originalCookie = $_COOKIE;
        $_COOKIE = [];
        parent::setUp();
        $this->pdo->exec('DELETE FROM image_tag; DELETE FROM image;');
        $this->uploadUrl = new Url(protocol: 'https', host: 'localhost', path: '/upload-image');
        $this->cleanImageStorage();
    }

    protected function tearDown(): void
    {
        $this->sessionService->destroySession();
        parent::tearDown();
        $this->cleanImageStorage();
        $_COOKIE = $this->originalCookie;
    }

    public function testPostAction_RequiresAuthentication(): void
    {
        $request = new Request(
            url: $this->uploadUrl,
            method: HttpMethod::POST,
            files: [
                'image' => [
                    'name' => 'test.jpg',
                    'tmp_name' => ROOT_DIR . '/tests/TestUtils/TestFiles/image.jpg',
                    'size' => 1024,
                    'type' => 'image/jpeg',
                    'error' => UPLOAD_ERR_OK,
                ]
            ]
        );

        $response = $this->simulateRequest($request);

        $this->assertEquals(HttpStatus::UNAUTHORIZED, $response->getStatus());
        $this->assertNull($response->getContent());
    }

    public function testPostActionAcceptsApiKeyWithoutSessionCookieAndAuditsUploadAsApi(): void
    {
        $this->loginUserWithAccessLevel(
            'api-upload-' . bin2hex(random_bytes(5)),
            'API Image Uploader',
            AccessLevel::WIZARD,
        );
        $userData = $this->sessionService->getUserData();
        self::assertNotNull($userData);
        $apiKey = $this->apiKeyService->generateForUser($userData->id);
        $existingImageIds = array_map(
            static fn($entity) => $entity->getId(),
            $this->imageEntityService->findByStatus(ImageStatus::PENDING),
        );
        $this->sessionService->destroySession();
        self::assertSame(PHP_SESSION_NONE, session_status());
        self::assertArrayNotHasKey(SessionService::SESSION_NAME, $_COOKIE);
        self::assertNull($this->sessionService->getUserData());
        $tmpPath = ROOT_DIR . '/tests/TestUtils/TestFiles/image.jpg';

        $request = new Request(
            url: $this->uploadUrl,
            method: HttpMethod::POST,
            headers: [
                HttpHeader::AUTHORIZATION->value => 'Bearer ' . $apiKey,
                HttpHeader::ORIGIN->value => 'https://' . $this->uploadUrl->host,
                HttpHeader::SEC_FETCH_SITE->value => 'same-origin',
            ],
            files: [
                'image' => [
                    'name' => 'test.jpg',
                    'tmp_name' => $tmpPath,
                    'size' => 1024,
                    'type' => 'image/jpeg',
                    'error' => UPLOAD_ERR_OK,
                ],
            ],
        );
        self::assertFalse(
            self::hasHeader($request->headers, 'Cookie'),
            'An API-key-only request must not carry a session cookie.',
        );

        $cookieEmitterProperty = new ReflectionProperty(SessionService::class, 'cookieEmitter');
        $previousCookieEmitter = $cookieEmitterProperty->getValue($this->sessionService);
        $emittedCookies = [];
        $this->sessionService->setCookieEmitter(
            static function (string $name, string $value, array $options) use (&$emittedCookies): void {
                $emittedCookies[] = ['name' => $name, 'value' => $value, 'options' => $options];
            },
        );
        try {
            $response = $this->simulateRequest($request);
        } finally {
            $cookieEmitterProperty->setValue($this->sessionService, $previousCookieEmitter);
        }

        self::assertSame([], $emittedCookies, 'API key authentication must not emit a session cookie.');
        self::assertSame(HttpStatus::CREATED, $response->getStatus());
        self::assertSame(PHP_SESSION_NONE, session_status(), 'API key authentication must not start a PHP session.');
        self::assertArrayNotHasKey(SessionService::SESSION_NAME, $_COOKIE);
        $responseHeaders = $response->getHeaders();
        self::assertSame(HttpContentType::JSON->value, $responseHeaders[HttpHeader::CONTENT_TYPE->value]);
        self::assertFalse(self::hasHeader($responseHeaders, 'Set-Cookie'));
        self::assertFalse(self::hasHeader($responseHeaders, 'WWW-Authenticate'));
        self::assertFalse(self::hasHeader($responseHeaders, 'Content-Disposition'));
        $uploadedEntities = array_values(array_filter(
            $this->imageEntityService->findByStatus(ImageStatus::PENDING),
            static fn($entity) => !in_array($entity->getId(), $existingImageIds, true),
        ));
        self::assertCount(1, $uploadedEntities);
        $imageId = $uploadedEntities[0]->getId();
        self::assertSame($userData->id, $uploadedEntities[0]->getUserId());

        $entries = $this->auditLogService->getRecentEntriesForUser($userData->id, 15);
        self::assertSame(AuditActionType::IMAGE_UPLOADED->value, $entries[0]['actionType']);
        self::assertSame('image_id=' . $imageId . ';via=api', $entries[0]['context']);
    }

    public function testPostActionAcceptsApiKeyAfterDiscardingStaleSessionCookie(): void
    {
        $this->loginUserWithAccessLevel(
            'api-upload-stale-' . bin2hex(random_bytes(5)),
            'Stale Cookie API Uploader',
            AccessLevel::WIZARD,
        );
        $userData = $this->sessionService->getUserData();
        self::assertNotNull($userData);
        $apiKey = $this->apiKeyService->generateForUser($userData->id);
        $this->sessionService->destroySession();

        $_COOKIE[SessionService::SESSION_NAME] = 'stale-session-id';
        $startupAction = new ResumeSessionStartupAction($this->sessionService, $this->sessionHandler);
        $startupAction->runAction();

        self::assertArrayNotHasKey(SessionService::SESSION_NAME, $_COOKIE);
        self::assertSame(PHP_SESSION_NONE, session_status());
        $tmpPath = ROOT_DIR . '/tests/TestUtils/TestFiles/image.jpg';
        $request = new Request(
            url: $this->uploadUrl,
            method: HttpMethod::POST,
            headers: [
                HttpHeader::AUTHORIZATION->value => 'Bearer ' . $apiKey,
                HttpHeader::ORIGIN->value => 'https://' . $this->uploadUrl->host,
                HttpHeader::SEC_FETCH_SITE->value => 'same-origin',
            ],
            files: [
                'image' => [
                    'name' => 'test.jpg',
                    'tmp_name' => $tmpPath,
                    'size' => 1024,
                    'type' => 'image/jpeg',
                    'error' => UPLOAD_ERR_OK,
                ],
            ],
        );
        self::assertFalse(self::hasHeader($request->headers, 'Cookie'));

        $response = $this->simulateRequest($request);

        self::assertSame(HttpStatus::CREATED, $response->getStatus());
        self::assertSame(PHP_SESSION_NONE, session_status());
        self::assertArrayNotHasKey(SessionService::SESSION_NAME, $_COOKIE);
    }

    public function testOptionsActionReturnsCorsPreflightHeadersWithoutAuthentication(): void
    {
        $request = new Request(
            url: $this->uploadUrl,
            method: HttpMethod::OPTIONS,
            headers: [
                HttpHeader::ORIGIN->value => 'https://app.example',
                'Access-Control-Request-Method' => 'POST',
                'Access-Control-Request-Headers' => 'authorization, content-type',
                HttpHeader::SEC_FETCH_SITE->value => 'cross-site',
            ],
        );

        $response = $this->simulateRequest($request);

        self::assertSame(HttpStatus::NO_CONTENT, $response->getStatus());
        self::assertNull($response->getContent());
        $headers = $response->getHeaders();
        self::assertSame('*', $headers[HttpHeader::ACCESS_CONTROL_ALLOW_ORIGIN->value]);
        self::assertSame('POST, OPTIONS', $headers[HttpHeader::ACCESS_CONTROL_ALLOW_METHODS->value]);
        self::assertSame(
            'Authorization, Content-Type',
            $headers[HttpHeader::ACCESS_CONTROL_ALLOW_HEADERS->value],
        );
        self::assertSame('86400', $headers[HttpHeader::ACCESS_CONTROL_MAX_AGE->value]);
        self::assertSame('POST, OPTIONS', $headers[HttpHeader::ALLOW->value]);
    }

    public function testPostActionAcceptsCrossOriginApiKeyUpload(): void
    {
        $this->loginUserWithAccessLevel(
            'api-upload-cross-origin-' . bin2hex(random_bytes(5)),
            'Cross-Origin API Uploader',
            AccessLevel::WIZARD,
        );
        $userData = $this->sessionService->getUserData();
        self::assertNotNull($userData);
        $apiKey = $this->apiKeyService->generateForUser($userData->id);
        $existingImageIds = array_map(
            static fn($entity) => $entity->getId(),
            $this->imageEntityService->findByStatus(ImageStatus::PENDING),
        );
        $this->sessionService->destroySession();
        $tmpPath = ROOT_DIR . '/tests/TestUtils/TestFiles/image.jpg';

        $request = new Request(
            url: $this->uploadUrl,
            method: HttpMethod::POST,
            headers: [
                HttpHeader::AUTHORIZATION->value => 'Bearer ' . $apiKey,
                HttpHeader::ORIGIN->value => 'https://app.example',
                HttpHeader::SEC_FETCH_SITE->value => 'cross-site',
            ],
            files: [
                'image' => [
                    'name' => 'test.jpg',
                    'tmp_name' => $tmpPath,
                    'size' => 1024,
                    'type' => 'image/jpeg',
                    'error' => UPLOAD_ERR_OK,
                ],
            ],
        );

        $response = $this->simulateRequest($request);

        self::assertSame(HttpStatus::CREATED, $response->getStatus());
        self::assertSame(
            '*',
            $response->getHeaders()[HttpHeader::ACCESS_CONTROL_ALLOW_ORIGIN->value],
        );
        self::assertSame(PHP_SESSION_NONE, session_status());
        $uploadedEntities = array_values(array_filter(
            $this->imageEntityService->findByStatus(ImageStatus::PENDING),
            static fn($entity) => !in_array($entity->getId(), $existingImageIds, true),
        ));
        self::assertCount(1, $uploadedEntities);
        self::assertSame($userData->id, $uploadedEntities[0]->getUserId());
        $entries = $this->auditLogService->getRecentEntriesForUser($userData->id, 15);
        self::assertSame(
            'image_id=' . $uploadedEntities[0]->getId() . ';via=api',
            $entries[0]['context'],
        );
    }

    public function testPostActionRejectsCrossOriginRequestWithoutApiKey(): void
    {
        $existingImageIds = array_map(
            static fn($entity) => $entity->getId(),
            $this->imageEntityService->findByStatus(ImageStatus::PENDING),
        );
        $tmpPath = ROOT_DIR . '/tests/TestUtils/TestFiles/image.jpg';

        $request = new Request(
            url: $this->uploadUrl,
            method: HttpMethod::POST,
            headers: [
                HttpHeader::ORIGIN->value => 'https://evil.example',
                HttpHeader::SEC_FETCH_SITE->value => 'cross-site',
            ],
            files: [
                'image' => [
                    'name' => 'test.jpg',
                    'tmp_name' => $tmpPath,
                    'size' => 1024,
                    'type' => 'image/jpeg',
                    'error' => UPLOAD_ERR_OK,
                ],
            ],
        );

        $response = $this->simulateRequest($request);

        self::assertSame(HttpStatus::FORBIDDEN, $response->getStatus());
        self::assertSame(
            '*',
            $response->getHeaders()[HttpHeader::ACCESS_CONTROL_ALLOW_ORIGIN->value],
        );
        $uploadedEntities = array_filter(
            $this->imageEntityService->findByStatus(ImageStatus::PENDING),
            static fn($entity) => !in_array($entity->getId(), $existingImageIds, true),
        );
        self::assertCount(0, $uploadedEntities);
        self::assertSame(PHP_SESSION_NONE, session_status());
    }

    public function testPostActionRejectsCrossOriginSessionUploadEvenWithApiKeyHeader(): void
    {
        $this->loginUserWithAccessLevel(
            'api-upload-cross-origin-session-' . bin2hex(random_bytes(5)),
            'Cross-Origin Session Uploader',
            AccessLevel::WIZARD,
        );
        $userData = $this->sessionService->getUserData();
        self::assertNotNull($userData);
        $apiKey = $this->apiKeyService->generateForUser($userData->id);
        $sessionId = session_id();
        self::assertNotSame('', $sessionId);
        $existingImageIds = array_map(
            static fn($entity) => $entity->getId(),
            $this->imageEntityService->findByStatus(ImageStatus::PENDING),
        );
        $tmpPath = ROOT_DIR . '/tests/TestUtils/TestFiles/image.jpg';

        $request = new Request(
            url: $this->uploadUrl,
            method: HttpMethod::POST,
            headers: [
                HttpHeader::AUTHORIZATION->value => 'Bearer ' . $apiKey,
                HttpHeader::ORIGIN->value => 'https://evil.example',
                HttpHeader::SEC_FETCH_SITE->value => 'cross-site',
                HttpHeader::COOKIE->value => SessionService::SESSION_NAME . '=' . $sessionId,
            ],
            files: [
                'image' => [
                    'name' => 'test.jpg',
                    'tmp_name' => $tmpPath,
                    'size' => 1024,
                    'type' => 'image/jpeg',
                    'error' => UPLOAD_ERR_OK,
                ],
            ],
        );

        $response = $this->simulateRequest($request);

        self::assertSame(HttpStatus::FORBIDDEN, $response->getStatus());
        self::assertSame(PHP_SESSION_ACTIVE, session_status());
        $uploadedEntities = array_filter(
            $this->imageEntityService->findByStatus(ImageStatus::PENDING),
            static fn($entity) => !in_array($entity->getId(), $existingImageIds, true),
        );
        self::assertCount(0, $uploadedEntities);
    }

    public function testPostActionRejectsUnknownApiKey(): void
    {
        $request = new Request(
            url: $this->uploadUrl,
            method: HttpMethod::POST,
            headers: [HttpHeader::AUTHORIZATION->value => 'Bearer ' . str_repeat('0', 64)],
            files: [],
        );

        $response = $this->simulateRequest($request);

        self::assertSame(HttpStatus::UNAUTHORIZED, $response->getStatus());
    }

    public function testPostActionForOrphanedApiKeyReturnsForbiddenWithoutDestroyingSession(): void
    {
        $this->loginUserWithAccessLevel(
            'api-upload-deleted-' . bin2hex(random_bytes(5)),
            'Deleted API Image Uploader',
            AccessLevel::WIZARD,
        );
        $userData = $this->sessionService->getUserData();
        self::assertNotNull($userData);
        $apiKey = $this->apiKeyService->generateForUser($userData->id);
        $deleteUser = $this->pdo->prepare('DELETE FROM user WHERE id = :id');
        $deleteUser->execute(['id' => $userData->id]);
        $this->sessionService->destroySession();
        $this->sessionService->startSession();

        $request = new Request(
            url: $this->uploadUrl,
            method: HttpMethod::POST,
            headers: [HttpHeader::AUTHORIZATION->value => 'Bearer ' . $apiKey],
        );
        $response = $this->simulateRequest($request);

        self::assertSame(HttpStatus::FORBIDDEN, $response->getStatus());
        self::assertSame(PHP_SESSION_ACTIVE, session_status(), 'API-key failures must not destroy an existing session.');
    }

    public function testPostAction_unreadableTmpFile_returnsInternalServerError(): void
    {
        $this->loginUser('testuser', 'Test User');
        $request = new Request(
            url: $this->uploadUrl,
            method: HttpMethod::POST,
            files: [
                'image' => [
                    'name' => 'test.jpg',
                    'tmp_name' => ROOT_DIR . '/tests/TestUtils/TestFiles/does-not-exist.jpg',
                    'size' => 1024,
                    'type' => 'image/jpeg',
                    'error' => UPLOAD_ERR_OK,
                ]
            ]
        );

        $response = $this->simulateRequest($request);

        $this->assertEquals(HttpStatus::INTERNAL_SERVER_ERROR, $response->getStatus());
    }

    public function testPostAction_unknownImageType_returnsBadRequest(): void
    {
        $this->loginUser('testuser', 'Test User');
        $tmpPath = ROOT_DIR . '/tests/TestUtils/TestFiles/forbidden.txt';

        $request = new Request(
            url: $this->uploadUrl,
            method: HttpMethod::POST,
            files: [
                'image' => [
                    'name' => 'test.txt',
                    'tmp_name' => $tmpPath,
                    'size' => filesize($tmpPath),
                    'type' => 'text/plain',
                    'error' => UPLOAD_ERR_OK,
                ]
            ]
        );

        $response = $this->simulateRequest($request);

        $this->assertEquals(HttpStatus::BAD_REQUEST, $response->getStatus());
    }

    public function testPostAction_SuccessfulImageUpload_returnsGenericMessageAndStoresChecksumNamedOriginal(): void
    {
        $this->loginUser('testuser', 'Test User');
        $existingImageIds = array_map(
            static fn($entity) => $entity->getId(),
            $this->imageEntityService->findByStatus(ImageStatus::PENDING)
        );
        $tmpPath = ROOT_DIR . '/tests/TestUtils/TestFiles/image.jpg';

        $request = new Request(
            url: $this->uploadUrl,
            method: HttpMethod::POST,
            files: [
                'image' => [
                    'name' => 'test.jpg',
                    'tmp_name' => $tmpPath,
                    'size' => 1024,
                    'type' => 'image/jpeg',
                    'error' => UPLOAD_ERR_OK,
                ]
            ]
        );

        $response = $this->simulateRequest($request);

        $this->assertEquals(HttpStatus::CREATED, $response->getStatus());
        $this->assertSame(HttpContentType::JSON->value, $response->getHeaders()[HttpHeader::CONTENT_TYPE->value]);

        $body = json_decode((string)$response->getContent(), true);
        $this->assertSame(['message' => 'Image uploaded successfully.'], $body);

        // The stored original is named <sha-256 checksum>.jpeg directly in upload storage.
        $pendingEntities = $this->imageEntityService->findByStatus(ImageStatus::PENDING);
        $uploadedEntities = array_values(array_filter(
            $pendingEntities,
            static fn($entity) => !in_array($entity->getId(), $existingImageIds, true)
        ));
        $this->assertCount(1, $uploadedEntities);
        $entity = $uploadedEntities[0];
        $checksum = $entity->getChecksum();
        $filename = $checksum . '.' . $entity->getOriginalExtension();
        $filePath = IMAGE_STORAGE_DIR . '/' . $filename;
        $this->assertFileExists($filePath);
        $this->assertSame($checksum, hash('sha256', (string)file_get_contents($filePath)));
        // The client-supplied name is no longer used
        $this->assertFileDoesNotExist(IMAGE_STORAGE_DIR . '/test.jpg');

        // A pending image record was created for the stored original
        $this->assertSame('jpeg', $entity->getOriginalExtension());
        $this->assertNull($entity->getThumbFilename());
        $this->assertNull($entity->getSampleFilename());
        $this->assertNull($entity->getDownloadFilename());
        $this->assertContains('tagme', $this->tagService->getTagsForImage($entity->getId()));
    }

    public function testPostAction_duplicateImage_returnsConflictAndDoesNotStoreIt(): void
    {
        $this->loginUser('testuser', 'Test User');
        $tmpPath = ROOT_DIR . '/tests/TestUtils/TestFiles/image.jpg';
        $request = $this->createUploadRequest([$tmpPath], ['test.jpg']);

        $firstResponse = $this->simulateRequest($request);
        self::assertSame(HttpStatus::CREATED, $firstResponse->getStatus());
        $pendingAfterFirstUpload = $this->imageEntityService->findByStatus(ImageStatus::PENDING);
        self::assertCount(1, $pendingAfterFirstUpload);

        $duplicateResponse = $this->simulateRequest($request);

        self::assertSame(HttpStatus::CONFLICT, $duplicateResponse->getStatus());
        self::assertCount(1, $this->imageEntityService->findByStatus(ImageStatus::PENDING));
        self::assertSame(
            $pendingAfterFirstUpload[0]->getChecksum(),
            hash('sha256', (string)file_get_contents($tmpPath)),
        );
    }

    public function testPostAction_batchContainingDuplicate_storesNoFilesAndReturnsConflict(): void
    {
        $this->loginUser('testuser', 'Test User');
        $jpegPath = ROOT_DIR . '/tests/TestUtils/TestFiles/image.jpg';
        $pngPath = ROOT_DIR . '/tests/TestUtils/TestFiles/image.png';
        self::assertSame(
            HttpStatus::CREATED,
            $this->simulateRequest($this->createUploadRequest([$jpegPath], ['first.jpg']))->getStatus(),
        );
        $pngChecksum = hash('sha256', (string)file_get_contents($pngPath));

        $response = $this->simulateRequest($this->createUploadRequest(
            [$jpegPath, $pngPath],
            ['duplicate.jpg', 'new.png'],
        ));

        self::assertSame(HttpStatus::CONFLICT, $response->getStatus());
        self::assertCount(1, $this->imageEntityService->findByStatus(ImageStatus::PENDING));
        self::assertFileDoesNotExist(IMAGE_STORAGE_DIR . '/' . $pngChecksum . '.png');
    }

    public function testPostAction_sameFileTwiceInOneBatch_returnsConflictAndStoresNothing(): void
    {
        $this->loginUser('testuser', 'Test User');
        $pngPath = ROOT_DIR . '/tests/TestUtils/TestFiles/image.png';

        $response = $this->simulateRequest($this->createUploadRequest(
            [$pngPath, $pngPath],
            ['first.png', 'second.png'],
        ));

        self::assertSame(HttpStatus::CONFLICT, $response->getStatus());
        self::assertCount(0, $this->imageEntityService->findByStatus(ImageStatus::PENDING));
        self::assertFileDoesNotExist(
            IMAGE_STORAGE_DIR . '/' . hash('sha256', (string)file_get_contents($pngPath)) . '.png'
        );
    }

    public function testPostAction_multipleImages_uploadsEachImage(): void
    {
        $this->loginUser('testuser', 'Test User');
        $existingImageIds = array_map(
            static fn($entity) => $entity->getId(),
            $this->imageEntityService->findByStatus(ImageStatus::PENDING)
        );
        $jpegPath = ROOT_DIR . '/tests/TestUtils/TestFiles/image.jpg';
        $pngPath = ROOT_DIR . '/tests/TestUtils/TestFiles/image.png';

        $request = new Request(
            url: $this->uploadUrl,
            method: HttpMethod::POST,
            files: [
                'image' => [
                    'name' => ['first.jpg', 'second.png'],
                    'tmp_name' => [$jpegPath, $pngPath],
                    'size' => [filesize($jpegPath), filesize($pngPath)],
                    'type' => ['image/jpeg', 'image/png'],
                    'error' => [UPLOAD_ERR_OK, UPLOAD_ERR_OK],
                ],
            ],
        );

        $response = $this->simulateRequest($request);

        $this->assertEquals(HttpStatus::CREATED, $response->getStatus());
        $uploadedEntities = array_values(array_filter(
            $this->imageEntityService->findByStatus(ImageStatus::PENDING),
            static fn($entity) => !in_array($entity->getId(), $existingImageIds, true)
        ));
        $this->assertCount(2, $uploadedEntities);
        $extensions = array_map(static fn($entity) => $entity->getOriginalExtension(), $uploadedEntities);
        sort($extensions);
        $this->assertSame(['jpeg', 'png'], $extensions);
    }

    /**
     * @param list<string> $tmpPaths
     * @param list<string> $names
     */
    private function createUploadRequest(array $tmpPaths, array $names): Request
    {
        $files = [];
        foreach ($tmpPaths as $index => $tmpPath) {
            $files[] = [
                'name' => $names[$index],
                'tmp_name' => $tmpPath,
                'size' => filesize($tmpPath),
                'type' => 'application/octet-stream',
                'error' => UPLOAD_ERR_OK,
            ];
        }

        if (count($files) === 1) {
            $image = $files[0];
        } else {
            $image = [
                'name' => array_column($files, 'name'),
                'tmp_name' => array_column($files, 'tmp_name'),
                'size' => array_column($files, 'size'),
                'type' => array_column($files, 'type'),
                'error' => array_column($files, 'error'),
            ];
        }

        return new Request(
            url: $this->uploadUrl,
            method: HttpMethod::POST,
            files: ['image' => $image],
        );
    }

    /** @param array<array-key, mixed> $headers */
    private static function hasHeader(array $headers, string $headerName): bool
    {
        foreach (array_keys($headers) as $name) {
            if (is_string($name) && strcasecmp($name, $headerName) === 0) {
                return true;
            }
        }
        return false;
    }

    private function cleanImageStorage(): void
    {
        $dir = IMAGE_STORAGE_DIR;
        if (!is_dir($dir)) {
            return;
        }
        $entries = scandir($dir);
        if ($entries === false) {
            return;
        }
        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $entryPath = $dir . '/' . $entry;
            if (is_dir($entryPath)) {
                $this->rmDirRecursive($entryPath);
            } else {
                unlink($entryPath);
            }
        }
    }

    private function rmDirRecursive(string $path): void
    {
        $entries = scandir($path);
        if ($entries === false) {
            return;
        }
        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $entryPath = $path . '/' . $entry;
            if (is_dir($entryPath)) {
                $this->rmDirRecursive($entryPath);
            } else {
                unlink($entryPath);
            }
        }
        rmdir($path);
    }
}
