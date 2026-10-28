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

readonly class ImageReviewController
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
        return new JsonResponse(
            ['images' => $this->imageReviewService->getReviewQueue()]
        );
    }

    #[Authenticated(AccessLevel::MOD)]
    public function approveAction(IdRequest $request): Response
    {
        return $this->processAction((int)$request->id, $this->imageReviewService->approve(...));
    }

    #[Authenticated(AccessLevel::MOD)]
    public function rejectAction(IdRequest $request): Response
    {
        return $this->processAction((int)$request->id, $this->imageReviewService->reject(...));
    }

    #[Authenticated(AccessLevel::MOD)]
    public function deleteAction(IdRequest $request): Response
    {
        $id = (int)$request->id;
        if (!$this->imageReviewService->queueForDeletion($id)) {
            return new Response(HttpStatus::NOT_FOUND);
        }

        $user = $this->sessionService->getUserData();
        if ($user !== null) {
            $this->auditLogService->record($user->id, AuditActionType::IMAGE_QUEUED_FOR_DELETION, "image_id=$id");
        }

        return new JsonResponse(status: HttpStatus::NO_CONTENT);
    }

    private function processAction(int $id, callable $action): Response
    {
        if (!$action($id)) {
            return new Response(HttpStatus::NOT_FOUND);
        }
        return new JsonResponse(status: HttpStatus::NO_CONTENT);
    }
}
