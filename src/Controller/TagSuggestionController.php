<?php

declare(strict_types=1);

namespace SmolCms\Controller;

use InvalidArgumentException;
use SmolCms\Data\Constant\AccessLevel;
use SmolCms\Data\Constant\HttpStatus;
use SmolCms\Data\Request\Request;
use SmolCms\Data\Request\TagSuggestionCreateRequest;
use SmolCms\Data\Response\JsonResponse;
use SmolCms\Data\Response\Response;
use SmolCms\Service\Core\Attribute\Authenticated;
use SmolCms\Service\Core\Session\SessionService;
use SmolCms\Service\DB\ImageEntityService;
use SmolCms\Service\Tag\TagSuggestionService;

readonly class TagSuggestionController
{
    public function __construct(
        private TagSuggestionService $tagSuggestionService,
        private ImageEntityService $imageEntityService,
        private SessionService $sessionService,
    )
    {
    }

    #[Authenticated(AccessLevel::FELLOW)]
    public function createAction(TagSuggestionCreateRequest $request): Response
    {
        $imageId = $request->imageId ?? 0;
        $tagNames = self::parseTagNames($request->tags ?? '');
        if ($tagNames === null) {
            return new Response(HttpStatus::BAD_REQUEST);
        }
        if ($this->imageEntityService->findOneById($imageId) === null) {
            return new Response(HttpStatus::NOT_FOUND);
        }
        $user = $this->sessionService->getUserData();
        if ($user === null) {
            return new Response(HttpStatus::UNAUTHORIZED);
        }

        try {
            $suggested = $this->tagSuggestionService->createSuggestions($imageId, $user->id, $tagNames);
        } catch (InvalidArgumentException) {
            return new Response(HttpStatus::BAD_REQUEST);
        }

        return new JsonResponse(['suggested' => $suggested], HttpStatus::CREATED);
    }

    #[Authenticated(AccessLevel::MOD)]
    public function queueAction(Request $request): Response
    {
        return new JsonResponse(['suggestions' => $this->tagSuggestionService->getQueue()]);
    }

    #[Authenticated(AccessLevel::MOD)]
    public function approveAction(Request $request): Response
    {
        return $this->processAction($request, $this->tagSuggestionService->approve(...));
    }

    #[Authenticated(AccessLevel::MOD)]
    public function rejectAction(Request $request): Response
    {
        return $this->processAction($request, $this->tagSuggestionService->reject(...));
    }

    private function processAction(Request $request, callable $action): Response
    {
        $suggestionId = self::parseId(($request->getParams ?? [])['id'] ?? null);
        if ($suggestionId === null) {
            return new Response(HttpStatus::BAD_REQUEST);
        }
        if (!$action($suggestionId)) {
            return new Response(HttpStatus::NOT_FOUND);
        }
        return new JsonResponse(status: HttpStatus::NO_CONTENT);
    }

    /** @return list<string>|null */
    private static function parseTagNames(string $tags): ?array
    {
        $tagNames = [];
        foreach (explode(',', $tags) as $tagName) {
            $tagName = trim($tagName);
            if ($tagName !== '') {
                $tagNames[] = $tagName;
            }
        }
        return $tagNames === [] ? null : $tagNames;
    }

    private static function parseId(mixed $id): ?int
    {
        if (!is_string($id) || !ctype_digit($id)) {
            return null;
        }
        $digits = ltrim($id, '0');
        if ($digits === '') {
            return null;
        }
        $maximum = (string)PHP_INT_MAX;
        if (strlen($digits) > strlen($maximum)
            || (strlen($digits) === strlen($maximum) && strcmp($digits, $maximum) > 0)) {
            return null;
        }
        return (int)$digits;
    }
}
