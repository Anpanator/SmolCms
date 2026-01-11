<?php
declare(strict_types=1);

namespace SmolCms\Test\Service\Core;

use PHPUnit\Framework\MockObject\MockObject;
use SmolCms\Data\Persistence\UserEntity;
use SmolCms\Service\Core\Authentication\AuthenticationService;
use SmolCms\TestUtils\SimpleTestCase;

class AuthenticationServiceTest extends SimpleTestCase
{
    private AuthenticationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new AuthenticationService();
    }

    public function testAuthenticate_ReturnsTrueOnValidPassword(): void
    {
        $password = 'correct-password';
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        /** @var UserEntity|MockObject $user */
        $user = $this->createMock(UserEntity::class);
        $user->method('getPassword')->willReturn($hashedPassword);

        $result = $this->service->authenticate($password, $user);

        $this->assertTrue($result, 'Authentication should succeed with the correct password.');
    }

    public function testAuthenticate_ReturnsFalseOnInvalidPassword(): void
    {
        $password = 'wrong-password';
        $hashedPassword = password_hash('correct-password', PASSWORD_DEFAULT);

        /** @var UserEntity|MockObject $user */
        $user = $this->createMock(UserEntity::class);
        $user->method('getPassword')->willReturn($hashedPassword);

        $result = $this->service->authenticate($password, $user);

        $this->assertFalse($result, 'Authentication should fail with an incorrect password.');
    }
}
