<?php
declare(strict_types=1);

namespace SmolCms\Controller;

use SmolCms\Data\Constant\AccessLevel;
use SmolCms\Data\Constant\HttpHeader;
use SmolCms\Data\Constant\HttpStatus;
use SmolCms\Data\Request\EmptyRequest;
use SmolCms\Data\Request\ImageUploadRequest;
use SmolCms\Data\Response\JsonResponse;
use SmolCms\Data\Response\Response;
use SmolCms\Service\Core\Attribute\Authenticated;
use SmolCms\Service\Core\Attribute\CorsEnabled;
use SmolCms\Service\Image\ImageTypeDetectionFacade;
use SmolCms\Service\Image\ImageUploadService;
use SmolCms\Service\Validation\ImageUploadValidator;

readonly class ImageUploadController
{
    public function __construct(
        private ImageUploadValidator     $imageUploadValidator,
        private ImageTypeDetectionFacade $imageTypeDetectionFacade,
        private ImageUploadService       $imageUploadService,
    )
    {
    }

    #[CorsEnabled]
    public function optionsAction(EmptyRequest $request): Response
    {
        return new Response(
            status: HttpStatus::NO_CONTENT,
            headers: [HttpHeader::ALLOW->value => 'POST, OPTIONS'],
        );
    }

    #[CorsEnabled]
    #[Authenticated(AccessLevel::NOVICE, apiEnabled: true)]
    public function postAction(ImageUploadRequest $request): Response
    {
        $validationStatus = $this->imageUploadValidator->getStatus($request);
        if ($validationStatus !== HttpStatus::OK) {
            return new Response($validationStatus);
        }

        // Validate every file's readability and type before storing any of the batch.
        // Keep only paths and types here so a batch does not retain all file contents
        // in memory at once.
        $uploads = [];
        foreach ($this->imageUploadValidator->getFiles($request) as $file) {
            $tmpPath = $file['tmp_name'];
            $fileContent = is_readable($tmpPath) ? file_get_contents($tmpPath) : false;
            if ($fileContent === false) {
                return new Response(HttpStatus::INTERNAL_SERVER_ERROR);
            }

            $imageType = $this->imageTypeDetectionFacade->getImageType($fileContent);
            if ($imageType === null) {
                return new Response(HttpStatus::BAD_REQUEST);
            }

            $uploads[] = ['tmpPath' => $tmpPath, 'imageType' => $imageType];
        }

        foreach ($uploads as $upload) {
            $tmpPath = $upload['tmpPath'];
            $fileContent = is_readable($tmpPath) ? file_get_contents($tmpPath) : false;
            if ($fileContent === false) {
                return new Response(HttpStatus::INTERNAL_SERVER_ERROR);
            }

            $this->imageUploadService->upload($fileContent, $upload['imageType']);
        }

        return new JsonResponse(
            ['message' => 'Image uploaded successfully.'],
            HttpStatus::CREATED
        );
    }
}
