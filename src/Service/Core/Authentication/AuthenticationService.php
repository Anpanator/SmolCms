<?php
declare(strict_types=1);

namespace SmolCms\Service\Core\Authentication;

use SensitiveParameter;
use SmolCms\Service\DB\UserService;

readonly class AuthenticationService
{

    public function __construct(
        private UserService $userService,
    )
    {
    }

    public function authenticate(string $loginName, #[SensitiveParameter] string $password): bool
    {
        $user = $this->userService->findOneByLoginName($loginName);
        if ($user === null) {
            return false;
        }

        $passwordValid = password_verify($password, $user->getPassword());
        if ($passwordValid) {
            // TODO: Handle rehashing
            return true;
        }

        return false;
    }
}