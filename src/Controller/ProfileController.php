<?php

declare(strict_types=1);

namespace SmolCms\Controller;

use SmolCms\Data\Constant\AccessLevel;
use SmolCms\Data\Constant\HttpStatus;
use SmolCms\Data\Request\EmptyRequest;
use SmolCms\Data\Request\IdRequest;
use SmolCms\Data\Response\JsonResponse;
use SmolCms\Data\Response\Response;
use SmolCms\Service\Core\Attribute\Authenticated;
use SmolCms\Service\Core\Session\SessionService;
use SmolCms\Service\DB\ApiKeyService;
use SmolCms\Service\DB\AuditLogService;
use SmolCms\Service\DB\TagApprovalService;
use SmolCms\Service\DB\UserService;
use SmolCms\Service\Image\ImageQueryService;

readonly class ProfileController
{
    private const int RECENT_UPLOADS_LIMIT = 15;
    private const string PROFILE_DATE_FORMAT = 'Y-m-d H:i:s';

    public function __construct(
        private SessionService $sessionService,
        private TagApprovalService $tagApprovalService,
        private ImageQueryService $imageQueryService,
        private UserService $userService,
        private AuditLogService $auditLogService,
        private ApiKeyService $apiKeyService,
    )
    {
    }

    #[Authenticated(AccessLevel::NOVICE)]
    public function getAction(EmptyRequest $request): Response
    {
        $user = $this->sessionService->getUserData();
        if ($user === null) {
            return new Response(HttpStatus::UNAUTHORIZED);
        }
        return new JsonResponse([
            'approvedTagSuggestions' => $this->tagApprovalService->countForUser($user->id),
            'recentUploads' => $this->imageQueryService->getRecentUploadsForUser($user->id, self::RECENT_UPLOADS_LIMIT),
            'hasApiKey' => $this->apiKeyService->hasForUser($user->id),
        ]);
    }

    #[Authenticated(AccessLevel::WIZARD)]
    public function generateApiKeyAction(EmptyRequest $request): Response
    {
        $user = $this->sessionService->getUserData();
        if ($user === null) {
            return new Response(HttpStatus::UNAUTHORIZED);
        }
        return new JsonResponse(['apiKey' => $this->apiKeyService->generateForUser($user->id)], HttpStatus::CREATED);
    }

    #[Authenticated(AccessLevel::WIZARD)]
    public function deleteApiKeyAction(EmptyRequest $request): Response
    {
        $user = $this->sessionService->getUserData();
        if ($user === null) {
            return new Response(HttpStatus::UNAUTHORIZED);
        }
        if (!$this->apiKeyService->deleteForUser($user->id)) {
            return new Response(HttpStatus::NOT_FOUND);
        }
        return new JsonResponse(status: HttpStatus::NO_CONTENT);
    }

    public function getUserAction(IdRequest $request): Response
    {
        $sessionUser = $this->sessionService->getUserData();
        $viewer = $sessionUser === null ? null : $this->userService->findOneById($sessionUser->id);

        $userId = (int)$request->id;

        $user = $this->userService->findOneById($userId);
        if ($user === null) {
            return new Response(HttpStatus::NOT_FOUND);
        }

        $targetId = $user->getId() ?? $userId;
        $viewerIsTarget = $viewer !== null && $viewer->getId() === $targetId;
        $canSeeModActions = $viewer !== null
            && !$viewerIsTarget
            && $viewer->getAccessLevel()->value >= AccessLevel::MOD->value
            && $user->getAccessLevel()->value < $viewer->getAccessLevel()->value;

        return new JsonResponse([
            'user' => [
                'id' => $targetId,
                'displayName' => $user->getDisplayName(),
                'accessLevel' => $user->getAccessLevel()->value,
                'registerDate' => $user->getRegisterDate()->format(self::PROFILE_DATE_FORMAT),
            ],
            'approvedTagSuggestions' => $this->tagApprovalService->countForUser($targetId),
            'recentUploads' => $this->imageQueryService->getRecentUploadsForUser(
                $targetId,
                self::RECENT_UPLOADS_LIMIT,
                $viewerIsTarget,
            ),
            'modActions' => $canSeeModActions
                ? $this->auditLogService->getRecentEntriesForUser($targetId, self::RECENT_UPLOADS_LIMIT)
                : [],
        ]);
    }
}
