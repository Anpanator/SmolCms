<?php

declare(strict_types=1);

namespace SmolCms\Controller;

use SmolCms\Data\Constant\AccessLevel;
use SmolCms\Data\Constant\HttpStatus;
use SmolCms\Data\Request\UserListRequest;
use SmolCms\Data\Request\UserManagementUpdateRequest;
use SmolCms\Data\Response\JsonResponse;
use SmolCms\Data\Response\Response;
use SmolCms\Exception\UserUpdateForbiddenException;
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
    public function getListAction(UserListRequest $request): Response
    {
        $page = $request->page === null ? 1 : (int)$request->page;
        $query = $request->q ?? '';
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
        $id = (int)$request->id;
        $displayName = $request->displayName;
        $requestedAccessLevel = AccessLevel::from($request->accessLevel);

        $target = $this->userService->findOneById($id);
        if ($target === null) {
            return new Response(HttpStatus::NOT_FOUND);
        }
        $actor = $this->sessionService->getUserData();
        if ($actor === null) {
            return new Response(HttpStatus::UNAUTHORIZED);
        }
        if ($id === $actor->id
            || $target->getAccessLevel()->value >= $actor->accessLevel->value
            || $requestedAccessLevel->value >= $actor->accessLevel->value) {
            return new Response(HttpStatus::FORBIDDEN);
        }

        $loginName = $request->loginName;
        if ($loginName !== null) {
            $existingUser = $this->userService->findOneByLoginName($loginName);
            if ($existingUser !== null && $existingUser->getId() !== $id) {
                return new Response(HttpStatus::CONFLICT);
            }
        }

        try {
            $updated = $this->userService->updateUser(
                $id,
                $displayName,
                $requestedAccessLevel,
                $actor->id,
                $loginName,
            );
        } catch (UserUpdateForbiddenException) {
            return new Response(HttpStatus::FORBIDDEN);
        }
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

}
