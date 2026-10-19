<?php

declare(strict_types=1);

namespace SmolCms\Controller;

use SmolCms\Data\Constant\AccessLevel;
use SmolCms\Data\Constant\HttpStatus;
use SmolCms\Data\Request\Request;
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
    public function getAction(Request $request): Response
    {
        $params = $request->getParams ?? [];
        $page = self::parsePage($params['page'] ?? null);
        $query = $params['q'] ?? '';
        if ($page === null || !is_string($query)) {
            return new Response(HttpStatus::BAD_REQUEST);
        }

        return new JsonResponse($this->auditLogService->getEntries($page, $query));
    }

    private static function parsePage(mixed $page): ?int
    {
        if ($page === null) {
            return 1;
        }
        if (!is_string($page) || !ctype_digit($page)) {
            return null;
        }
        $digits = ltrim($page, '0');
        if ($digits === '') {
            return null;
        }
        $maximum = (string)(intdiv(PHP_INT_MAX, 30) + 1);
        if (strlen($digits) > strlen($maximum)
            || (strlen($digits) === strlen($maximum) && strcmp($digits, $maximum) > 0)) {
            return null;
        }
        return (int)$digits;
    }
}
