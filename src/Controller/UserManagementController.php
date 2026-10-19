<?php

declare(strict_types=1);

namespace SmolCms\Controller;

use SmolCms\Data\Constant\AccessLevel;
use SmolCms\Data\Constant\HttpStatus;
use SmolCms\Data\Request\Request;
use SmolCms\Data\Request\UserManagementUpdateRequest;
use SmolCms\Data\Response\JsonResponse;
use SmolCms\Data\Response\Response;
use SmolCms\Service\Core\Attribute\Authenticated;
use SmolCms\Service\Core\Session\SessionService;
use SmolCms\Service\DB\UserService;

readonly class UserManagementController
{
    public function __construct(
        private UserService $userService,
        private SessionService $sessionService,
    )
    {
    }

    #[Authenticated(AccessLevel::MOD)]
    public function getListAction(Request $request): Response
    {
        $params = $request->getParams ?? [];
        $page = self::parsePage($params['page'] ?? null);
        $query = $params['q'] ?? '';
        if ($page === null || !is_string($query)) {
            return new Response(HttpStatus::BAD_REQUEST);
        }

        $result = $this->userService->searchUsers($page, $query);
        $result['users'] = array_map(
            static fn(array $user): array => [
                'id' => $user['id'],
                'loginName' => $user['loginName'],
                'displayName' => $user['displayName'],
                'accessLevel' => $user['accessLevel']->value,
                'registerDate' => $user['registerDate'],
            ],
            $result['users'],
        );
        return new JsonResponse($result);
    }

    #[Authenticated(AccessLevel::MOD)]
    public function updateAction(UserManagementUpdateRequest $request): Response
    {
        $id = self::parseId(($request->rawRequest->getParams ?? [])['id'] ?? null);
        if ($id === null) {
            return new Response(HttpStatus::BAD_REQUEST);
        }
        $displayName = $request->displayName ?? '';
        $requestedAccessLevel = $request->accessLevel === null
            ? null
            : AccessLevel::tryFrom($request->accessLevel);
        if ($requestedAccessLevel === null) {
            return new Response(HttpStatus::BAD_REQUEST);
        }

        $target = $this->userService->findOneById($id);
        if ($target === null) {
            return new Response(HttpStatus::NOT_FOUND);
        }
        $actor = $this->sessionService->getUserData();
        if ($actor === null) {
            return new Response(HttpStatus::UNAUTHORIZED);
        }
        if ($target->getAccessLevel()->value > $actor->accessLevel->value) {
            return new Response(HttpStatus::FORBIDDEN);
        }

        $loginName = $request->loginName;
        if ($loginName !== null) {
            $existingUser = $this->userService->findOneByLoginName($loginName);
            if ($existingUser !== null && $existingUser->getId() !== $id) {
                return new Response(HttpStatus::CONFLICT);
            }
        }

        $updated = $this->userService->updateUser($id, $displayName, $requestedAccessLevel, $loginName);
        if ($updated === null) {
            return new Response(HttpStatus::NOT_FOUND);
        }
        return new JsonResponse([
            'user' => [
                'id' => $updated->getId(),
                'loginName' => $updated->getLoginName(),
                'displayName' => $updated->getDisplayName(),
                'accessLevel' => $updated->getAccessLevel()->value,
            ],
        ]);
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

    private static function parseId(mixed $id): ?int
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
