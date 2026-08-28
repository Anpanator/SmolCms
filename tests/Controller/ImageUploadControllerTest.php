<?php
declare(strict_types=1);

namespace SmolCms\Test\Controller;

use PHPUnit\Framework\MockObject\MockObject;
use SmolCms\Config\Templates\HtmlPageConfigFactory;
use SmolCms\Controller\ImageUploadController;
use SmolCms\Data\Business\Service;
use SmolCms\Data\Business\Url;
use SmolCms\Data\Constant\HttpMethod;
use SmolCms\Data\Constant\HttpStatus;
use SmolCms\Data\Request\Request;
use SmolCms\Data\Response\RedirectResponse;
use SmolCms\Service\Core\ContextService;
use SmolCms\Service\Core\TemplateService;
use SmolCms\Service\Image\ImageProcessorFacade;
use SmolCms\Service\Image\ImageTypeDetectionFacade;
use SmolCms\Service\Validation\ImageUploadValidator;
use SmolCms\TestUtils\Attributes\Stub;
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

    #[Stub(ImageUploadValidator::class)]
    private ImageUploadValidator|MockObject $imageUploadValidator;

    #[Stub(ImageTypeDetectionFacade::class)]
    private ImageTypeDetectionFacade|MockObject $imageTypeDetectionFacade;

    #[Stub(ImageProcessorFacade::class)]
    private ImageProcessorFacade|MockObject $imageProcessorFacade;

    #[Stub(TemplateService::class)]
    private TemplateService|MockObject $templateService;

    #[Stub(HtmlPageConfigFactory::class)]
    private HtmlPageConfigFactory|MockObject $htmlPageConfigFactory;

    #[Stub(ContextService::class)]
    private ContextService|MockObject $contextService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->uploadUrl = new Url(protocol: 'https', host: 'localhost', path: '/upload-image');
        $this->cleanImageStorage();
    }

    private ImageUploadController $controller;

    protected function setUpController(): void
    {
        $this->controller = new ImageUploadController(
            $this->imageUploadValidator,
            $this->imageTypeDetectionFacade,
            $this->imageProcessorFacade,
            $this->templateService,
            $this->htmlPageConfigFactory,
            $this->contextService,
        );
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
    }

    public function testGetAction_RequiresAuthentication(): void
    {
        $request = new Request(url: $this->uploadUrl, method: HttpMethod::GET);

        $response = $this->simulateRequest($request);

        $this->assertSame(HttpStatus::UNAUTHORIZED, $response->getStatus());
    }

    public function testGetAction_RendersUploadFormForAuthenticatedUser(): void
    {
        $this->loginUser('get-upload-user', 'Upload User');

        $request = new Request(url: $this->uploadUrl, method: HttpMethod::GET);

        $response = $this->simulateRequest($request);

        $this->assertSame(HttpStatus::OK, $response->getStatus());
        $this->assertStringContainsString('Upload an image', (string)$response->getContent());
        $this->assertStringContainsString('multipart/form-data', (string)$response->getContent());
    }

    public function testPostAction_SuccessfulImageUpload(): void
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

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertEquals(HttpStatus::SEE_OTHER, $response->getStatus());
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
            unlink($dir . '/' . $entry);
        }
    }
}
