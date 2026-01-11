<?php
declare(strict_types=1);

namespace SmolCms\Service\Core\Authentication;

use SensitiveParameter;
use SmolCms\Data\Persistence\UserEntity;
use SmolCms\Service\DB\UserService;

readonly class AuthenticationService
{
    public function __construct(private UserService $userService)
    {
    }

    public function authenticate(#[SensitiveParameter] string $password, UserEntity $user): bool
    {
        $passwordValid = password_verify($password, $user->getPassword());
        if ($passwordValid) {
            // TODO: Rehash in dedicated service
            if (password_needs_rehash($user->getPassword(), PASSWORD_DEFAULT)) {
                $user->setPassword(password_hash($password, PASSWORD_DEFAULT));
                $this->userService->saveOrUpdate($user);
            }
            return true;
        }

        return false;
    }
}