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

readonly class ImageReviewController
{
    public function __construct(
        private ImageReviewService $imageReviewService,
        private AuditLogService $auditLogService,
        private SessionService  $sessionService,
    )
    {
    }

    #[Authenticated(AccessLevel::MOD)]
    public function queueAction(Request $request): Response
    {
        return new JsonResponse(
            ['images' => $this->imageReviewService->getReviewQueue()]
        );
    }

    #[Authenticated(AccessLevel::MOD)]
    public function approveAction(Request $request): Response
    {
        return $this->processAction($request, $this->imageReviewService->approve(...));
    }

    #[Authenticated(AccessLevel::MOD)]
    public function rejectAction(Request $request): Response
    {
        return $this->processAction($request, $this->imageReviewService->reject(...));
    }

    #[Authenticated(AccessLevel::MOD)]
    public function deleteAction(Request $request): Response
    {
        $id = self::parseImageId(($request->getParams ?? [])['id'] ?? null);
        if ($id === null) {
            return new Response(HttpStatus::BAD_REQUEST);
        }
        if (!$this->imageReviewService->delete($id)) {
            return new Response(HttpStatus::NOT_FOUND);
        }

        $user = $this->sessionService->getUserData();
        if ($user !== null) {
            $this->auditLogService->record($user->id, AuditActionType::IMAGE_DELETED, "image_id=$id");
        }

        return new JsonResponse(status: HttpStatus::NO_CONTENT);
    }

    private function processAction(Request $request, callable $action): Response
    {
        $id = self::parseImageId(($request->getParams ?? [])['id'] ?? null);
        if ($id === null) {
            return new Response(HttpStatus::BAD_REQUEST);
        }
        if (!$action($id)) {
            return new Response(HttpStatus::NOT_FOUND);
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
