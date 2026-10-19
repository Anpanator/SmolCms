<?php
declare(strict_types=1);

namespace SmolCms\Controller;

use SmolCms\Data\Constant\AccessLevel;
use SmolCms\Data\Constant\HttpStatus;
use SmolCms\Data\Request\Request;
use SmolCms\Data\Response\JsonResponse;
use SmolCms\Data\Response\Response;
use SmolCms\Service\Core\Attribute\Authenticated;
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

    #[Authenticated(AccessLevel::NOVICE)]
    public function postAction(Request $request): Response
    {
        // TODO: Move this type of validation to attribute, and receive ValidatedRequest instead?
        $validationStatus = $this->imageUploadValidator->getStatus($request);
        if ($validationStatus !== HttpStatus::OK) {
            return new Response($validationStatus);
        }

        $file = $request->files['image'];
        $tmpPath = $file['tmp_name'];
        $fileContent = is_readable($tmpPath) ? file_get_contents($tmpPath) : false;
        if ($fileContent === false) {
            return new Response(HttpStatus::INTERNAL_SERVER_ERROR);
        }

        $imageType = $this->imageTypeDetectionFacade->getImageType($fileContent);
        if ($imageType === null) {
            return new Response(HttpStatus::BAD_REQUEST);
        }

        $this->imageUploadService->upload($fileContent, $imageType);

        return new JsonResponse(
            ['message' => 'Image uploaded successfully.'],
            HttpStatus::CREATED
        );
    }
}
