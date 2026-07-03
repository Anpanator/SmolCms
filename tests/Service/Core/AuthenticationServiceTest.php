<?php
declare(strict_types=1);

namespace SmolCms\Test\Service\Core;

use PHPUnit\Framework\MockObject\MockObject;
use SmolCms\Data\Persistence\UserEntity;
use SmolCms\Service\Core\Authentication\AuthenticationService;
use SmolCms\Service\Core\Authentication\PasswordService;
use SmolCms\TestUtils\Attributes\Mock;
use SmolCms\TestUtils\SimpleTestCase;

class AuthenticationServiceTest extends SimpleTestCase
{
    private AuthenticationService $service;
    #[Mock(PasswordService::class)]
    private PasswordService|MockObject $passwordService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new AuthenticationService($this->passwordService);
    }

    public function testAuthenticate_ReturnsTrueOnValidPassword(): void
    {
        $password = 'correct-password';

        /** @var UserEntity|MockObject $user */
        $user = $this->createMock(UserEntity::class);

        $this->passwordService
            ->expects($this->once())
            ->method('verifyAndRehashIfNeeded')
            ->with($password, $user)
            ->willReturn(true)
            ->seal();

        $result = $this->service->authenticate($password, $user);

        $this->assertTrue($result, 'Authentication should succeed with the correct password.');
    }

    public function testAuthenticate_ReturnsFalseOnInvalidPassword(): void
    {
        $password = 'wrong-password';

        /** @var UserEntity|MockObject $user */
        $user = $this->createMock(UserEntity::class);

        $this->passwordService
            ->expects($this->once())
            ->method('verifyAndRehashIfNeeded')
            ->with($password, $user)
            ->willReturn(false)
            ->seal();

        $result = $this->service->authenticate($password, $user);

        $this->assertFalse($result, 'Authentication should fail with an incorrect password.');
    }
}
