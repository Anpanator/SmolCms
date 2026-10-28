<?php

declare(strict_types=1);

namespace SmolCms\Controller;

use InvalidArgumentException;
use SmolCms\Data\Constant\AccessLevel;
use SmolCms\Data\Constant\AuditActionType;
use SmolCms\Data\Constant\HttpStatus;
use SmolCms\Data\Request\EmptyRequest;
use SmolCms\Data\Request\TagCategoryCreateRequest;
use SmolCms\Data\Request\TagCreateRequest;
use SmolCms\Data\Request\TagDeleteRequest;
use SmolCms\Data\Request\TagListRequest;
use SmolCms\Data\Request\TagSuggestionSearchRequest;
use SmolCms\Data\Response\JsonResponse;
use SmolCms\Data\Response\Response;
use SmolCms\Service\Core\Attribute\Authenticated;
use SmolCms\Service\Core\Session\SessionService;
use SmolCms\Service\DB\AuditLogService;
use SmolCms\Service\Tag\TagService;
use SmolCms\Service\Tag\UserTagUsageService;

readonly class TagApiController
{
    public function __construct(
        private TagService $tagService,
        private AuditLogService $auditLogService,
        private SessionService  $sessionService,
        private UserTagUsageService $userTagUsageService,
    )
    {
    }

    public function getSuggestionsAction(TagSuggestionSearchRequest $request): Response
    {
        return new JsonResponse(['tags' => $this->tagService->suggestTags($request->q ?? '')]);
    }

    #[Authenticated(AccessLevel::MOD)]
    public function getListAction(TagListRequest $request): Response
    {
        $page = $request->page === null ? 1 : (int)$request->page;
        $query = $request->q ?? '';
        return new JsonResponse($this->tagService->browseTags($page, $query));
    }

    #[Authenticated(AccessLevel::NOVICE)]
    public function getQuickTagsAction(EmptyRequest $request): Response
    {
        $user = $this->sessionService->getUserData();
        if ($user === null) {
            return new Response(HttpStatus::UNAUTHORIZED);
        }

        return new JsonResponse($this->userTagUsageService->getQuickTags($user->id));
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
    public function getCategoriesAction(EmptyRequest $request): Response
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

    #[Authenticated(AccessLevel::ADMIN)]
    public function deleteTagAction(TagDeleteRequest $request): Response
    {
        $tagName = TagService::normalizeName($request->name ?? '');
        if ($this->tagService->getUsageCount($tagName) === null) {
            return new Response(HttpStatus::NOT_FOUND);
        }
        if (!$this->tagService->deleteTag($tagName)) {
            return new Response(HttpStatus::CONFLICT);
        }

        $user = $this->sessionService->getUserData();
        if ($user !== null) {
            $this->auditLogService->record(
                $user->id,
                AuditActionType::TAG_DELETED,
                "tag=$tagName",
            );
        }

        return new JsonResponse(status: HttpStatus::NO_CONTENT);
    }
}
