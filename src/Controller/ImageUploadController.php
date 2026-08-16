<?php
declare(strict_types=1);

namespace SmolCms\Controller;

use SmolCms\Data\Constant\AccessLevel;
use SmolCms\Data\Constant\HttpStatus;
use SmolCms\Data\Request\Request;
use SmolCms\Data\Response\RedirectResponse;
use SmolCms\Data\Response\Response;
use SmolCms\Service\Core\Attribute\Authenticated;
use SmolCms\Service\Image\ImageProcessorInterface;
use SmolCms\Service\Image\ImageTypeDetectionFacade;
use SmolCms\Service\Validation\ImageUploadValidator;

readonly class ImageUploadController
{
    public function __construct(
        private ImageUploadValidator     $imageUploadValidator,
        private ImageTypeDetectionFacade $imageTypeDetectionFacade,
        private ImageProcessorInterface  $imageProcessor,
    )
    {
    }

    #[Authenticated(AccessLevel::NOVICE)]
    public function postAction(Request $request): Response
    {
        if (!$this->imageUploadValidator->validate($request)) {
            return new Response(HttpStatus::BAD_REQUEST);
        }

        $file = $request->files['image'];
        $tmpPath = $file['tmp_name'];
        $filename = basename($file['name']);

        $fileContent = file_get_contents($tmpPath);
        if ($fileContent === false) {
            return new Response(HttpStatus::INTERNAL_SERVER_ERROR);
        }

        $imageType = $this->imageTypeDetectionFacade->getImageType($fileContent);
        if ($imageType === null) {
            return new Response(HttpStatus::BAD_REQUEST);
        }

        $this->imageProcessor->process($fileContent, $filename);

        return new RedirectResponse();
    }
}