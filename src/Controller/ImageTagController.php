<?php

declare(strict_types=1);

namespace SmolCms\Controller;

use InvalidArgumentException;
use SmolCms\Data\Constant\AccessLevel;
use SmolCms\Data\Constant\AuditActionType;
use SmolCms\Data\Constant\HttpStatus;
use SmolCms\Data\Request\ImageTagRemoveRequest;
use SmolCms\Data\Request\ImageTagsRequest;
use SmolCms\Data\Request\Request;
use SmolCms\Data\Response\JsonResponse;
use SmolCms\Data\Response\Response;
use SmolCms\Exception\TagmeRemovalBlockedException;
use SmolCms\Service\Core\Attribute\Authenticated;
use SmolCms\Service\Core\Session\SessionService;
use SmolCms\Service\DB\AuditLogService;
use SmolCms\Service\DB\ImageEntityService;
use SmolCms\Service\Tag\TagService;

readonly class ImageTagController
{
    public function __construct(
        private TagService $tagService,
        private ImageEntityService $imageEntityService,
        private AuditLogService $auditLogService,
        private SessionService $sessionService,
    )
    {
    }

    #[Authenticated(AccessLevel::NOVICE)]
    public function addAction(ImageTagsRequest $request): Response
    {
        $imageId = $request->imageId ?? 0;
        $tagNames = self::parseTagNames($request->tags ?? '');
        if ($tagNames === null) {
            return new Response(HttpStatus::BAD_REQUEST);
        }
        if ($this->imageEntityService->findOneById($imageId) === null) {
            return new Response(HttpStatus::NOT_FOUND);
        }

        $result = $this->tagService->addTagsToImageWithChanges($imageId, $tagNames);
        $user = $this->sessionService->getUserData();
        if ($user === null) {
            return new Response(HttpStatus::UNAUTHORIZED);
        }
        foreach ($result['newlyAttached'] as $tagName) {
            $this->auditLogService->record(
                $user->id,
                AuditActionType::TAG_ADDED,
                "image_id=$imageId;tag=$tagName",
            );
        }

        return new JsonResponse([
            'applied' => $result['applied'],
            'unknown' => $result['unknown'],
        ]);
    }

    #[Authenticated(AccessLevel::NOVICE)]
    public function removeAction(ImageTagRemoveRequest $request): Response
    {
        $imageId = $request->imageId ?? 0;
        $tagName = $request->tag ?? '';
        if ($this->imageEntityService->findOneById($imageId) === null) {
            return new Response(HttpStatus::NOT_FOUND);
        }

        try {
            $normalizedTagName = TagService::normalizeName($tagName);
            $removed = $this->tagService->removeTagFromImage($imageId, $normalizedTagName);
        } catch (TagmeRemovalBlockedException) {
            return new Response(HttpStatus::CONFLICT);
        } catch (InvalidArgumentException) {
            return new Response(HttpStatus::BAD_REQUEST);
        }
        if (!$removed) {
            return new Response(HttpStatus::NOT_FOUND);
        }

        $user = $this->sessionService->getUserData();
        if ($user === null) {
            return new Response(HttpStatus::UNAUTHORIZED);
        }
        $this->auditLogService->record(
            $user->id,
            AuditActionType::TAG_REMOVED,
            "image_id=$imageId;tag=$normalizedTagName",
        );

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
}
