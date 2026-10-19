<?php
declare(strict_types=1);

namespace SmolCms\Test\Service\Validation;

use PHPUnit\Framework\Attributes\DataProvider;
use SmolCms\Data\Business\Url;
use SmolCms\Data\Constant\HttpMethod;
use SmolCms\Data\Constant\HttpStatus;
use SmolCms\Data\Request\Request;
use SmolCms\Service\Validation\ImageUploadValidator;
use SmolCms\TestUtils\SimpleTestCase;

class ImageUploadValidatorTest extends SimpleTestCase
{
    private ImageUploadValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = new ImageUploadValidator();
    }

    public static function uploadErrorStatusProvider(): array
    {
        return [
            // Note: UPLOAD_ERR_OK cannot be mapped to OK here — the final is_uploaded_file()
            // check rejects non-SAPI uploads (see testGetStatus_nonUploadPath_returnsBadRequest).
            'ini size' => [UPLOAD_ERR_INI_SIZE, HttpStatus::PAYLOAD_TOO_LARGE],
            'form size' => [UPLOAD_ERR_FORM_SIZE, HttpStatus::PAYLOAD_TOO_LARGE],
            'no file' => [UPLOAD_ERR_NO_FILE, HttpStatus::BAD_REQUEST],
            'partial' => [UPLOAD_ERR_PARTIAL, HttpStatus::BAD_REQUEST],
            'no tmp dir' => [UPLOAD_ERR_NO_TMP_DIR, HttpStatus::INTERNAL_SERVER_ERROR],
            'cant write' => [UPLOAD_ERR_CANT_WRITE, HttpStatus::INTERNAL_SERVER_ERROR],
            'extension' => [UPLOAD_ERR_EXTENSION, HttpStatus::INTERNAL_SERVER_ERROR],
            'unknown error code' => [999, HttpStatus::BAD_REQUEST],
        ];
    }

    #[DataProvider('uploadErrorStatusProvider')]
    public function testGetStatus_mapsUploadErrorToSpecificStatus(int $uploadError, HttpStatus $expected): void
    {
        $request = $this->createUploadRequest(['error' => $uploadError]);
        $this->assertEquals($expected, $this->validator->getStatus($request));
    }

    public function testGetStatus_noFiles_returnsBadRequest(): void
    {
        $request = new Request(
            url: $this->createUploadUrl(),
            method: HttpMethod::POST,
            files: null,
        );
        $this->assertEquals(HttpStatus::BAD_REQUEST, $this->validator->getStatus($request));
    }

    public function testGetStatus_missingImageField_returnsBadRequest(): void
    {
        $request = new Request(
            url: $this->createUploadUrl(),
            method: HttpMethod::POST,
            files: ['other' => ['name' => 'x', 'tmp_name' => '/tmp/x', 'error' => UPLOAD_ERR_OK]],
        );
        $this->assertEquals(HttpStatus::BAD_REQUEST, $this->validator->getStatus($request));
    }

    public function testGetStatus_moreThanTenImages_returnsBadRequest(): void
    {
        $request = new Request(
            url: $this->createUploadUrl(),
            method: HttpMethod::POST,
            files: [
                'image' => [
                    'name' => array_fill(0, 11, 'test.jpg'),
                    'tmp_name' => array_fill(0, 11, '/tmp/phpupload'),
                    'error' => array_fill(0, 11, UPLOAD_ERR_OK),
                ],
            ],
        );

        $this->assertEquals(HttpStatus::BAD_REQUEST, $this->validator->getStatus($request));
    }

    public static function malformedFileProvider(): array
    {
        return [
            'not an array' => ['not-an-array'],
            'missing tmp_name' => [(['name' => 'test.jpg', 'error' => UPLOAD_ERR_OK])],
            'missing name' => [(['tmp_name' => '/tmp/whatever', 'error' => UPLOAD_ERR_OK])],
        ];
    }

    #[DataProvider('malformedFileProvider')]
    public function testGetStatus_malformedFileEntry_returnsBadRequest(array|string $file): void
    {
        $request = new Request(
            url: $this->createUploadUrl(),
            method: HttpMethod::POST,
            files: ['image' => $file],
        );
        $this->assertEquals(HttpStatus::BAD_REQUEST, $this->validator->getStatus($request));
    }

    public function testGetStatus_nonUploadPath_returnsBadRequest(): void
    {
        // Outside a real SAPI upload, is_uploaded_file() rejects regular paths
        $request = $this->createUploadRequest(['tmp_name' => __FILE__]);
        $this->assertEquals(HttpStatus::BAD_REQUEST, $this->validator->getStatus($request));
    }

    public function testValidate_returnsTrueOnlyForOkStatus(): void
    {
        $okRequest = $this->createUploadRequest(['error' => UPLOAD_ERR_OK, 'tmp_name' => __FILE__]);
        self::assertFalse($this->validator->validate($okRequest));
        // validate() delegates to getStatus(): an oversized upload cannot become valid
        $sizeRequest = $this->createUploadRequest(['error' => UPLOAD_ERR_INI_SIZE]);
        self::assertFalse($this->validator->validate($sizeRequest));
    }

    private function createUploadUrl(): Url
    {
        return new Url(protocol: 'https', host: 'localhost', path: '/upload-image');
    }

    private function createUploadRequest(array|null $fileData): Request
    {
        $file = $fileData === null ? null : array_merge(
            ['name' => 'test.jpg', 'tmp_name' => '/tmp/phpupload', 'error' => UPLOAD_ERR_OK],
            $fileData,
        );
        return new Request(
            url: $this->createUploadUrl(),
            method: HttpMethod::POST,
            files: $file === null ? null : ['image' => $file],
        );
    }
}
