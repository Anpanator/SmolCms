<?php

declare(strict_types=1);

namespace SmolCms\TestUtils\TestServices;

use SensitiveParameter;
use SmolCms\Data\Persistence\UserEntity;
use SmolCms\Service\Core\Authentication\PasswordService;

final readonly class TestPasswordService extends PasswordService
{
    public function hash(#[SensitiveParameter] string $password): string
    {
        return password_hash($password, PASSWORD_BCRYPT, ['cost' => 4]);
    }

    public function verifyAndRehashIfNeeded(#[SensitiveParameter] string $password, UserEntity $user): bool
    {
        if (!password_verify($password, $user->getPassword())) {
            return false;
        }

        return true;
    }
}
