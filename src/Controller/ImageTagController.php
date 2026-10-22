<?php

declare(strict_types=1);

namespace SmolCms\Controller;

use InvalidArgumentException;
use SmolCms\Data\Constant\AccessLevel;
use SmolCms\Data\Constant\AuditActionType;
use SmolCms\Data\Constant\HttpStatus;
use SmolCms\Data\Constant\TagInputLimits;
use SmolCms\Data\Request\ImageTagRemoveRequest;
use SmolCms\Data\Request\ImageTagsRequest;
use SmolCms\Data\Request\Request;
use SmolCms\Data\Response\JsonResponse;
use SmolCms\Data\Response\Response;
use SmolCms\Exception\TagmeRemovalBlockedException;
use SmolCms\Service\Core\Attribute\Authenticated;
use SmolCms\Service\Core\Session\SessionService;
use SmolCms\Service\DB\AuditLogService;
use SmolCms\Service\Image\ImageAccessPolicy;
use SmolCms\Service\Tag\TagService;
use SmolCms\Service\Tag\UserTagUsageService;

readonly class ImageTagController
{
    public function __construct(
        private TagService $tagService,
        private ImageAccessPolicy $imageAccessPolicy,
        private AuditLogService $auditLogService,
        private SessionService $sessionService,
        private UserTagUsageService $userTagUsageService,
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

        $result = $this->tagService->addTagsToImageWithChanges($imageId, $tagNames);
        foreach ($result['newlyAttached'] as $tagName) {
            $this->userTagUsageService->recordTagAdded($user->id, $tagName);
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

        $this->auditLogService->record(
            $user->id,
            AuditActionType::TAG_REMOVED,
            "image_id=$imageId;tag=$normalizedTagName",
        );
        $this->userTagUsageService->recordTagRemoved($user->id, $normalizedTagName);

        return new JsonResponse(status: HttpStatus::NO_CONTENT);
    }

    /** @return list<string>|null */
    private static function parseTagNames(string $tags): ?array
    {
        if (mb_strlen($tags, 'UTF-8') > TagInputLimits::MAX_TAG_LIST_LENGTH
            || substr_count($tags, ',') + 1 > TagInputLimits::MAX_TAG_COUNT) {
            return null;
        }

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
