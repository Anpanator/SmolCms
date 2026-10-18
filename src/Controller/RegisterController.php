<?php
declare(strict_types=1);

namespace SmolCms\Controller;

use DateTime;
use SmolCms\Data\Constant\AccessLevel;
use SmolCms\Data\Constant\HttpStatus;
use SmolCms\Data\Persistence\UserEntity;
use SmolCms\Data\Request\RegisterRequest;
use SmolCms\Data\Response\JsonResponse;
use SmolCms\Data\Response\Response;
use SmolCms\Service\Core\Authentication\PasswordService;
use SmolCms\Service\DB\UserService;

readonly class RegisterController
{
    public function __construct(
        private UserService     $userService,
        private PasswordService $passwordService,
    )
    {
    }

    public function postAction(RegisterRequest $request): Response
    {
        if ($request->password !== $request->passwordRepeat) {
            return new Response(HttpStatus::BAD_REQUEST);
        }

        $existingUser = $this->userService->findOneByLoginName($request->loginName);
        if ($existingUser !== null) {
            return new Response(HttpStatus::CONFLICT);
        }

        $user = new UserEntity(
            id: null,
            loginName: $request->loginName,
            password: $this->passwordService->hash($request->password),
            displayName: $request->displayName,
            state: 'active',
            registerDate: new DateTime(),
            lastLoginDate: null,
            accessLevel: AccessLevel::NOVICE,
        );

        $this->userService->saveOrUpdate($user);

        return new JsonResponse(['created' => true], HttpStatus::CREATED);
    }
}
