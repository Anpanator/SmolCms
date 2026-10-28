<?php
declare(strict_types=1);

namespace SmolCms\Controller;

use SmolCms\Data\Constant\AccessLevel;
use SmolCms\Data\Constant\AuditActionType;
use SmolCms\Data\Constant\HttpStatus;
use SmolCms\Data\Request\Request;
use SmolCms\Data\Response\JsonResponse;
use SmolCms\Data\Response\Response;
use SmolCms\Service\Core\Attribute\Authenticated;
use SmolCms\Service\Core\Session\SessionService;
use SmolCms\Service\DB\AuditLogService;
use SmolCms\Service\Image\ImageReviewService;

/**
 * Delete queue: images that moderators removed from the regular collection.
 * They stay hidden until restored or deleted for real from this queue.
 */
readonly class ImageDeleteQueueController
{
    public function __construct(
        private ImageReviewService $imageReviewService,
        private AuditLogService    $auditLogService,
        private SessionService     $sessionService,
    )
    {
    }

    #[Authenticated(AccessLevel::MOD)]
    public function queueAction(Request $request): Response
    {
        return new JsonResponse(['images' => $this->imageReviewService->getDeletionQueue()]);
    }

    #[Authenticated(AccessLevel::MOD)]
    public function restoreAction(Request $request): Response
    {
        return $this->processAction(
            $request,
            $this->imageReviewService->restore(...),
            AuditActionType::IMAGE_RESTORED,
        );
    }

    #[Authenticated(AccessLevel::MOD)]
    public function deleteAction(Request $request): Response
    {
        return $this->processAction(
            $request,
            $this->imageReviewService->deleteQueued(...),
            AuditActionType::IMAGE_DELETED,
        );
    }

    private function processAction(Request $request, callable $action, AuditActionType $auditActionType): Response
    {
        $id = self::parseImageId(($request->getParams ?? [])['id'] ?? null);
        if ($id === null) {
            return new Response(HttpStatus::BAD_REQUEST);
        }
        if (!$action($id)) {
            return new Response(HttpStatus::NOT_FOUND);
        }

        $user = $this->sessionService->getUserData();
        if ($user !== null) {
            $this->auditLogService->record($user->id, $auditActionType, "image_id=$id");
        }

        return new JsonResponse(status: HttpStatus::NO_CONTENT);
    }

    private static function parseImageId(mixed $id): ?int
    {
        if (!is_string($id) || !ctype_digit($id)) {
            return null;
        }
        $intId = (int)$id;
        return $intId > 0 ? $intId : null;
    }
}
