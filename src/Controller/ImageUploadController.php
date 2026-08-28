<?php
declare(strict_types=1);

namespace SmolCms\Controller;

use SmolCms\Config\Templates\HtmlPageConfigFactory;
use SmolCms\Config\Templates\SimpleContentComponentConfig;
use SmolCms\Data\Constant\AccessLevel;
use SmolCms\Data\Constant\ContextKey;
use SmolCms\Data\Constant\HttpMethod;
use SmolCms\Data\Constant\HttpStatus;
use SmolCms\Data\Constant\RouteEnum;
use SmolCms\Data\Request\Request;
use SmolCms\Data\Response\RedirectResponse;
use SmolCms\Data\Response\Response;
use SmolCms\Service\Core\Attribute\Authenticated;
use SmolCms\Service\Core\ContextService;
use SmolCms\Service\Core\TemplateService;
use SmolCms\Service\Image\ImageProcessorFacade;
use SmolCms\Service\Image\ImageTypeDetectionFacade;
use SmolCms\Service\Validation\ImageUploadValidator;
use SmolCms\Template\Component\ImageUploadFormComponent;

readonly class ImageUploadController
{
    public function __construct(
        private ImageUploadValidator     $imageUploadValidator,
        private ImageTypeDetectionFacade $imageTypeDetectionFacade,
        private ImageProcessorFacade  $imageProcessorFacade,
        private TemplateService       $templateService,
        private HtmlPageConfigFactory $htmlPageConfigFactory,
        private ContextService        $contextService,
    )
    {
    }

    #[Authenticated(AccessLevel::NOVICE)]
    public function getAction(Request $request): Response
    {
        $this->contextService->setContext(ContextKey::PAGE_TITLE, 'Upload Image');
        return $this->templateService->generateResponse(
            $this->htmlPageConfigFactory->wrap(
                new SimpleContentComponentConfig(
                    content: 'Upload an image',
                    extraComponents: [
                        ImageUploadFormComponent::class => [RouteEnum::UPLOAD_IMAGE, HttpMethod::POST],
                    ],
                ),
            )
        );
    }

    #[Authenticated(AccessLevel::NOVICE)]
    public function postAction(Request $request): Response
    {
        // TODO: Move this type of validation to attribute, and receive ValidatedRequest instead?
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

        $this->imageProcessorFacade->processImage($fileContent, $filename);

        return new RedirectResponse();
    }
}
