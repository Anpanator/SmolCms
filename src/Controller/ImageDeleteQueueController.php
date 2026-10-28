<?php
declare(strict_types=1);

namespace SmolCms\Controller;

use SmolCms\Data\Constant\AccessLevel;
use SmolCms\Data\Constant\AuditActionType;
use SmolCms\Data\Constant\HttpStatus;
use SmolCms\Data\Request\EmptyRequest;
use SmolCms\Data\Request\IdRequest;
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
    public function queueAction(EmptyRequest $request): Response
    {
        return new JsonResponse(['images' => $this->imageReviewService->getDeletionQueue()]);
    }

    #[Authenticated(AccessLevel::MOD)]
    public function restoreAction(IdRequest $request): Response
    {
        return $this->processAction(
            (int)$request->id,
            $this->imageReviewService->restore(...),
            AuditActionType::IMAGE_RESTORED,
        );
    }

    #[Authenticated(AccessLevel::MOD)]
    public function deleteAction(IdRequest $request): Response
    {
        return $this->processAction(
            (int)$request->id,
            $this->imageReviewService->deleteQueued(...),
            AuditActionType::IMAGE_DELETED,
        );
    }

    private function processAction(int $id, callable $action, AuditActionType $auditActionType): Response
    {
        if (!$action($id)) {
            return new Response(HttpStatus::NOT_FOUND);
        }

        $user = $this->sessionService->getUserData();
        if ($user !== null) {
            $this->auditLogService->record($user->id, $auditActionType, "image_id=$id");
        }

        return new JsonResponse(status: HttpStatus::NO_CONTENT);
    }
}
