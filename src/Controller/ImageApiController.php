<?php
declare(strict_types=1);

namespace SmolCms\Controller;

use SmolCms\Data\Constant\AccessLevel;
use SmolCms\Data\Constant\HttpStatus;
use SmolCms\Data\Request\Request;
use SmolCms\Data\Response\JsonResponse;
use SmolCms\Data\Response\Response;
use SmolCms\Service\Core\Attribute\Authenticated;
use SmolCms\Service\Core\Session\SessionService;
use SmolCms\Service\Image\ImageAccessPolicy;
use SmolCms\Service\Image\ImageQueryService;

readonly class ImageApiController
{
    public function __construct(
        private ImageQueryService $imageQueryService,
        private ImageAccessPolicy $imageAccessPolicy,
        private SessionService $sessionService,
    )
    {
    }

    public function getImageListAction(Request $request): Response
    {
        $params = $request->getParams ?? [];
        $page = self::parsePage($params['page'] ?? null);
        $tags = self::parseTags($params['tags'] ?? null);
        if ($page === null || $tags === null) {
            return new Response(HttpStatus::BAD_REQUEST);
        }

        $user = $this->sessionService->getUserData();
        $canViewAllPending = $user !== null && $user->accessLevel->value >= AccessLevel::MOD->value;
        return new JsonResponse($this->imageQueryService->getImagePage(
            $page,
            $tags,
            $user?->id,
            $canViewAllPending,
        ));
    }

    #[Authenticated(AccessLevel::NOVICE)]
    public function getImageDetailAction(Request $request): Response
    {
        $id = self::parseImageId(($request->getParams ?? [])['id'] ?? null);
        if ($id === null) {
            return new Response(HttpStatus::BAD_REQUEST);
        }

        $user = $this->sessionService->getUserData();
        if ($user === null) {
            return new Response(HttpStatus::UNAUTHORIZED);
        }
        $image = $this->imageAccessPolicy->findImage($id);
        if ($image === null) {
            return new Response(HttpStatus::NOT_FOUND);
        }
        if (!$this->imageAccessPolicy->canAccess($image, $user->accessLevel, $user->id)) {
            return new Response(HttpStatus::FORBIDDEN);
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

    private static function parsePage(mixed $page): ?int
    {
        if ($page === null) {
            return 1;
        }
        if (!is_string($page) || !ctype_digit($page)) {
            return null;
        }

        $digits = ltrim($page, '0');
        if ($digits === '') {
            return null;
        }
        $maximum = (string)(intdiv(PHP_INT_MAX, 25) + 1);
        if (strlen($digits) > strlen($maximum)
            || (strlen($digits) === strlen($maximum) && strcmp($digits, $maximum) > 0)) {
            return null;
        }

        $parsedPage = (int)$digits;
        return $parsedPage > 0 ? $parsedPage : null;
    }

    /** @return list<string>|null */
    private static function parseTags(mixed $tags): ?array
    {
        if ($tags === null) {
            return [];
        }
        if (!is_string($tags)) {
            return null;
        }

        $uniqueTags = [];
        foreach (explode(',', $tags) as $tagName) {
            $tagName = trim($tagName);
            if ($tagName === '') {
                continue;
            }
            $key = mb_strtolower($tagName, 'UTF-8');
            $uniqueTags[$key] ??= $tagName;
        }
        return array_values($uniqueTags);
    }
}
