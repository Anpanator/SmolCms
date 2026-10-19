<?php

declare(strict_types=1);

namespace SmolCms\Controller;

use InvalidArgumentException;
use SmolCms\Data\Constant\AccessLevel;
use SmolCms\Data\Constant\HttpStatus;
use SmolCms\Data\Request\Request;
use SmolCms\Data\Request\TagCategoryCreateRequest;
use SmolCms\Data\Request\TagCreateRequest;
use SmolCms\Data\Response\JsonResponse;
use SmolCms\Data\Response\Response;
use SmolCms\Service\Core\Attribute\Authenticated;
use SmolCms\Service\Tag\TagService;

readonly class TagApiController
{
    public function __construct(
        private TagService $tagService,
    )
    {
    }

    public function getSuggestionsAction(Request $request): Response
    {
        $query = ($request->getParams ?? [])['q'] ?? '';
        if (!is_string($query)) {
            return new Response(HttpStatus::BAD_REQUEST);
        }

        return new JsonResponse(['tags' => $this->tagService->suggestTags($query)]);
    }

    #[Authenticated(AccessLevel::MOD)]
    public function getListAction(Request $request): Response
    {
        $params = $request->getParams ?? [];
        $page = self::parsePage($params['page'] ?? null);
        $query = $params['q'] ?? '';
        if ($page === null || !is_string($query)) {
            return new Response(HttpStatus::BAD_REQUEST);
        }

        return new JsonResponse($this->tagService->browseTags($page, $query));
    }

    #[Authenticated(AccessLevel::MOD)]
    public function createTagAction(TagCreateRequest $request): Response
    {
        try {
            $tag = $this->tagService->createTag($request->name ?? '', $request->category);
        } catch (InvalidArgumentException) {
            return new Response(HttpStatus::BAD_REQUEST);
        }
        if ($tag === null) {
            return new Response(HttpStatus::CONFLICT);
        }

        return new JsonResponse(['tag' => $tag], HttpStatus::CREATED);
    }

    #[Authenticated(AccessLevel::MOD)]
    public function getCategoriesAction(Request $request): Response
    {
        return new JsonResponse(['categories' => $this->tagService->getCategoryNames()]);
    }

    #[Authenticated(AccessLevel::MOD)]
    public function createCategoryAction(TagCategoryCreateRequest $request): Response
    {
        try {
            $category = $this->tagService->createCategory($request->name ?? '');
        } catch (InvalidArgumentException) {
            return new Response(HttpStatus::BAD_REQUEST);
        }
        if ($category === null) {
            return new Response(HttpStatus::CONFLICT);
        }

        return new JsonResponse(['category' => $category], HttpStatus::CREATED);
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
        $maximum = (string)(intdiv(PHP_INT_MAX, 100) + 1);
        if (strlen($digits) > strlen($maximum)
            || (strlen($digits) === strlen($maximum) && strcmp($digits, $maximum) > 0)) {
            return null;
        }

        $parsedPage = (int)$digits;
        return $parsedPage > 0 ? $parsedPage : null;
    }
}
