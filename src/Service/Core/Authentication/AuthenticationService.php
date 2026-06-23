<?php
declare(strict_types=1);

namespace SmolCms\Service\Core\Authentication;

use SensitiveParameter;
use SmolCms\Data\Persistence\UserEntity;

readonly class AuthenticationService
{
    public function __construct(private PasswordService $passwordService)
    {
    }

    public function authenticate(#[SensitiveParameter] string $password, UserEntity $user): bool
    {
        return $this->passwordService->verifyAndRehashIfNeeded($password, $user);
    }
}