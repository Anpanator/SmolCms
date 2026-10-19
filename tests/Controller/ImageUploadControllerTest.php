<?php
declare(strict_types=1);

namespace SmolCms\Test\Controller;

use SmolCms\Data\Business\Service;
use SmolCms\Data\Business\Url;
use SmolCms\Data\Constant\HttpContentType;
use SmolCms\Data\Constant\HttpHeader;
use SmolCms\Data\Constant\HttpMethod;
use SmolCms\Data\Constant\HttpStatus;
use SmolCms\Data\Constant\ImageStatus;
use SmolCms\Data\Request\Request;
use SmolCms\Service\DB\ImageEntityService;
use SmolCms\Service\Image\ImageTypeDetectionFacade;
use SmolCms\Service\Validation\ImageUploadValidator;
use SmolCms\TestUtils\Attributes\Autowire;
use SmolCms\TestUtils\FunctionalTestCase;

readonly class TestImageUploadValidator extends ImageUploadValidator
{
    public function validate(Request $request): bool
    {
        return true;
    }
}

class ImageUploadControllerTest extends FunctionalTestCase
{
    private Url $uploadUrl;

    #[Autowire]
    private ImageEntityService $imageEntityService;

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
        parent::setUp();
        $this->uploadUrl = new Url(protocol: 'https', host: 'localhost', path: '/upload-image');
        $this->cleanImageStorage();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->cleanImageStorage();
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

    public function testPostAction_SuccessfulImageUpload_storesChecksumNamedOriginalAndCreatesPendingRecord(): void
    {
        $this->loginUser('testuser', 'Test User');
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

        // The stored original is named <sha-256 checksum>.jpeg and lives in a two-char subdirectory
        $body = json_decode((string)$response->getContent(), true);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}\.jpeg$/', $body['filename']);
        $checksum = substr($body['filename'], 0, 64);
        $filePath = IMAGE_STORAGE_DIR . '/' . substr($checksum, 0, 2) . '/' . $body['filename'];
        $this->assertFileExists($filePath);
        $this->assertSame($checksum, hash('sha256', (string)file_get_contents($filePath)));
        // The client-supplied name is no longer used
        $this->assertFileDoesNotExist(IMAGE_STORAGE_DIR . '/test.jpg');

        // A pending image record was created for the stored original
        $pendingEntities = $this->imageEntityService->findByStatus(ImageStatus::PENDING);
        $matchingEntities = array_filter(
            $pendingEntities,
            static fn($entity) => $entity->getChecksum() === $checksum
        );
        $this->assertCount(1, $matchingEntities);
        $entity = array_values($matchingEntities)[0];
        $this->assertSame('jpeg', $entity->getOriginalExtension());
        $this->assertNull($entity->getThumbFilename());
        $this->assertNull($entity->getSampleFilename());
        $this->assertNull($entity->getDownloadFilename());
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
