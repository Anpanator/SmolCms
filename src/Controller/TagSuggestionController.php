<?php

declare(strict_types=1);

namespace SmolCms\Controller;

use InvalidArgumentException;
use SmolCms\Data\Constant\AccessLevel;
use SmolCms\Data\Constant\HttpStatus;
use SmolCms\Data\Request\EmptyRequest;
use SmolCms\Data\Request\IdRequest;
use SmolCms\Data\Request\TagSuggestionCreateRequest;
use SmolCms\Data\Response\JsonResponse;
use SmolCms\Data\Response\Response;
use SmolCms\Service\Core\Attribute\Authenticated;
use SmolCms\Service\Core\Session\SessionService;
use SmolCms\Service\Image\ImageAccessPolicy;
use SmolCms\Service\Tag\TagSuggestionService;

readonly class TagSuggestionController
{
    public function __construct(
        private TagSuggestionService $tagSuggestionService,
        private ImageAccessPolicy $imageAccessPolicy,
        private SessionService $sessionService,
    )
    {
    }

    #[Authenticated(AccessLevel::FELLOW)]
    public function createAction(TagSuggestionCreateRequest $request): Response
    {
        $imageId = $request->imageId;
        $tagNames = self::parseTagNames($request->tags);
        $user = $this->sessionService->getUserData();
        if ($user === null) {
            return new Response(HttpStatus::UNAUTHORIZED);
        }
        $image = $this->imageAccessPolicy->findImage($imageId);
        if ($image === null) {
            return new Response(HttpStatus::NOT_FOUND);
        }
        if (!$this->imageAccessPolicy->canAccess($image, $user->accessLevel, $user->id)) {
            return new Response(HttpStatus::FORBIDDEN);
        }

        try {
            $suggested = $this->tagSuggestionService->createSuggestions($imageId, $user->id, $tagNames);
        } catch (InvalidArgumentException) {
            return new Response(HttpStatus::BAD_REQUEST);
        }

        return new JsonResponse(['suggested' => $suggested], HttpStatus::CREATED);
    }

    #[Authenticated(AccessLevel::MOD)]
    public function queueAction(EmptyRequest $request): Response
    {
        return new JsonResponse(['suggestions' => $this->tagSuggestionService->getQueue()]);
    }

    #[Authenticated(AccessLevel::MOD)]
    public function approveAction(IdRequest $request): Response
    {
        return $this->processAction((int)$request->id, $this->tagSuggestionService->approve(...));
    }

    #[Authenticated(AccessLevel::MOD)]
    public function rejectAction(IdRequest $request): Response
    {
        return $this->processAction((int)$request->id, $this->tagSuggestionService->reject(...));
    }

    private function processAction(int $suggestionId, callable $action): Response
    {
        if (!$action($suggestionId)) {
            return new Response(HttpStatus::NOT_FOUND);
        }
        return new JsonResponse(status: HttpStatus::NO_CONTENT);
    }

    /** @return list<string> */
    private static function parseTagNames(string $tags): array
    {
        $tagNames = [];
        foreach (explode(',', $tags) as $tagName) {
            $tagName = trim($tagName);
            if ($tagName !== '') {
                $tagNames[] = $tagName;
            }
        }
        return $tagNames;
    }
}
