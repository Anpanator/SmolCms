<?php
declare(strict_types=1);

namespace SmolCms\Service\Core\Authentication;

use SensitiveParameter;
use SmolCms\Data\Persistence\UserEntity;
use SmolCms\Service\DB\UserService;

readonly class PasswordService
{
    public function __construct(private UserService $userService)
    {
    }

    public function hash(#[SensitiveParameter] string $password): string
    {
        return password_hash($password, PASSWORD_DEFAULT);
    }

    public function verifyAndRehashIfNeeded(#[SensitiveParameter] string $password, UserEntity $user): bool
    {
        if (!password_verify($password, $user->getPassword())) {
            return false;
        }

        if (password_needs_rehash($user->getPassword(), PASSWORD_DEFAULT)) {
            $user->setPassword($this->hash($password));
            $this->userService->saveOrUpdate($user);
        }

        return true;
    }
}
