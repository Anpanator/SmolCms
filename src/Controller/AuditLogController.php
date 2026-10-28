<?php

declare(strict_types=1);

namespace SmolCms\Controller;

use SmolCms\Data\Constant\AccessLevel;
use SmolCms\Data\Request\AuditLogListRequest;
use SmolCms\Data\Response\JsonResponse;
use SmolCms\Data\Response\Response;
use SmolCms\Service\Core\Attribute\Authenticated;
use SmolCms\Service\DB\AuditLogService;

readonly class AuditLogController
{
    public function __construct(
        private AuditLogService $auditLogService,
    )
    {
    }

    #[Authenticated(AccessLevel::MOD)]
    public function getAction(AuditLogListRequest $request): Response
    {
        $page = $request->page === null ? 1 : (int)$request->page;
        $query = $request->q ?? '';
        return new JsonResponse($this->auditLogService->getEntries($page, $query));
    }
}
