<?php
declare(strict_types=1);

namespace SmolCms\Controller;

use SmolCms\Data\Constant\AccessLevel;
use SmolCms\Data\Constant\HttpStatus;
use SmolCms\Data\Request\Request;
use SmolCms\Data\Response\JsonResponse;
use SmolCms\Data\Response\Response;
use SmolCms\Service\Core\Attribute\Authenticated;
use SmolCms\Service\Image\ImageQueryService;

readonly class ImageApiController
{
    public function __construct(
        private ImageQueryService $imageQueryService,
    )
    {
    }

    public function getImageListAction(Request $request): Response
    {
        return new JsonResponse(
            ['images' => $this->imageQueryService->getAcceptedImageSummaries()]
        );
    }

    #[Authenticated(AccessLevel::NOVICE)]
    public function getImageDetailAction(Request $request): Response
    {
        $id = self::parseImageId(($request->getParams ?? [])['id'] ?? null);
        if ($id === null) {
            return new Response(HttpStatus::BAD_REQUEST);
        }

        $detail = $this->imageQueryService->getImageDetail($id);
        if ($detail === null) {
            return new Response(HttpStatus::NOT_FOUND);
        }

        return new JsonResponse(['image' => $detail]);
    }

    private static function parseImageId(mixed $id): ?int
    {
        if (!is_string($id) || !ctype_digit($id)) {
            return null;
        }
        $intId = (int)$id;
        return $intId > 0 ? $intId : null;
    }
}
