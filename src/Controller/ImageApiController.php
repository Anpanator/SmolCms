<?php
declare(strict_types=1);

namespace SmolCms\Controller;

use SmolCms\Data\Constant\AccessLevel;
use SmolCms\Data\Constant\HttpStatus;
use SmolCms\Data\Request\IdRequest;
use SmolCms\Data\Request\ImageListRequest;
use SmolCms\Data\Request\ImageNeighborRequest;
use SmolCms\Data\Response\JsonResponse;
use SmolCms\Data\Response\Response;
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

    public function getImageListAction(ImageListRequest $request): Response
    {
        $page = $request->page === null ? 1 : (int)$request->page;
        $tags = self::parseTags($request->tags);

        $user = $this->sessionService->getUserData();
        $canViewAllPending = $user !== null && $user->accessLevel->value >= AccessLevel::MOD->value;
        return new JsonResponse($this->imageQueryService->getImagePage(
            $page,
            $tags,
            $user?->id,
            $canViewAllPending,
        ));
    }

    public function getImageDetailAction(IdRequest $request): Response
    {
        $id = (int)$request->id;

        $user = $this->sessionService->getUserData();
        $image = $this->imageAccessPolicy->findImage($id);
        if ($image === null) {
            return new Response(HttpStatus::NOT_FOUND);
        }
        if (!$this->imageAccessPolicy->canAccessImageDetail(
            $image,
            $user?->accessLevel ?? AccessLevel::NOVICE,
            $user?->id,
        )) {
            return new Response(HttpStatus::FORBIDDEN);
        }

        $detail = $this->imageQueryService->getImageDetail($id);
        if ($detail === null) {
            return new Response(HttpStatus::NOT_FOUND);
        }

        return new JsonResponse(['image' => $detail]);
    }

    public function getImageNeighborAction(ImageNeighborRequest $request): Response
    {
        $id = (int)$request->id;
        $direction = $request->direction;

        $user = $this->sessionService->getUserData();
        $image = $this->imageAccessPolicy->findImage($id);
        if ($image === null) {
            return new Response(HttpStatus::NOT_FOUND);
        }
        if (!$this->imageAccessPolicy->canAccessImageDetail(
            $image,
            $user?->accessLevel ?? AccessLevel::NOVICE,
            $user?->id,
        )) {
            return new Response(HttpStatus::FORBIDDEN);
        }

        return new JsonResponse(['id' => $this->imageQueryService->getNeighborId(
            $id,
            $direction === 'next',
            $user?->id,
            $user !== null && $user->accessLevel->value >= AccessLevel::MOD->value,
        )]);
    }

    /** @return list<string> */
    private static function parseTags(?string $tags): array
    {
        if ($tags === null) {
            return [];
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
