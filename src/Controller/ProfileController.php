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
use SmolCms\Service\DB\TagApprovalService;
use SmolCms\Service\Image\ImageQueryService;

readonly class ProfileController
{
    private const int RECENT_UPLOADS_LIMIT = 15;

    public function __construct(
        private SessionService $sessionService,
        private TagApprovalService $tagApprovalService,
        private ImageQueryService $imageQueryService,
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
}
