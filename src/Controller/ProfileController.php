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
    )
    {
    }

    #[Authenticated(AccessLevel::NOVICE)]
    public function getAction(Request $request): Response
    {
        $user = $this->sessionService->getUserData();
        if ($user === null) {
            return new Response(HttpStatus::UNAUTHORIZED);
        }
        return new JsonResponse([
            'approvedTagSuggestions' => $this->tagApprovalService->countForUser($user->id),
            'recentUploads' => $this->imageQueryService->getRecentUploadsForUser($user->id, self::RECENT_UPLOADS_LIMIT),
        ]);
    }

    public function getUserAction(Request $request): Response
    {
        $userId = self::parsePositiveId($request->getParams['id'] ?? null);
        if ($userId === null) {
            return new Response(HttpStatus::BAD_REQUEST);
        }

        $user = $this->userService->findOneById($userId);
        if ($user === null) {
            return new Response(HttpStatus::NOT_FOUND);
        }

        $targetId = $user->getId() ?? $userId;
        $viewer = $this->sessionService->getUserData();
        $viewerIsTarget = $viewer !== null && $viewer->id === $targetId;
        $canSeeModActions = $viewer !== null
            && !$viewerIsTarget
            && $viewer->accessLevel->value >= AccessLevel::MOD->value
            && $user->getAccessLevel()->value < $viewer->accessLevel->value;

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

    private static function parsePositiveId(mixed $id): ?int
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
