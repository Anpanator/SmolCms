<?php
declare(strict_types=1);

namespace SmolCms\Service\Core\Authentication;

use SensitiveParameter;
use SmolCms\Data\Persistence\UserEntity;

readonly class AuthenticationService
{
    public function authenticate(#[SensitiveParameter] string $password, UserEntity $user): bool
    {
        $passwordValid = password_verify($password, $user->getPassword());
        if ($passwordValid) {
            // TODO: Handle rehashing
            return true;
        }

        return false;
    }
}