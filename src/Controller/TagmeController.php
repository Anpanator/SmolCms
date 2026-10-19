<?php

declare(strict_types=1);

namespace SmolCms\Controller;

use SmolCms\Data\Constant\AccessLevel;
use SmolCms\Data\Constant\HttpStatus;
use SmolCms\Data\Request\Request;
use SmolCms\Data\Response\JsonResponse;
use SmolCms\Data\Response\Response;
use SmolCms\Service\Core\Attribute\Authenticated;
use SmolCms\Service\DB\ImageEntityService;
use SmolCms\Service\Image\ImageQueryService;

readonly class TagmeController
{
    public function __construct(
        private ImageEntityService $imageEntityService,
        private ImageQueryService $imageQueryService,
    )
    {
    }

    #[Authenticated(AccessLevel::NOVICE)]
    public function getRandomAction(Request $request): Response
    {
        $image = $this->imageEntityService->findRandomAcceptedImageWithTagName('tagme');
        if ($image === null || $image->getId() === null) {
            return new Response(HttpStatus::NOT_FOUND);
        }
        $detail = $this->imageQueryService->getImageDetail($image->getId());
        if ($detail === null) {
            return new Response(HttpStatus::NOT_FOUND);
        }
        return new JsonResponse(['image' => $detail]);
    }
}
