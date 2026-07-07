<?php
declare(strict_types=1);

namespace SmolCms\Test\Service\Core;

use PHPUnit\Framework\MockObject\MockObject;
use SmolCms\Data\Persistence\UserEntity;
use SmolCms\Service\Core\Authentication\PasswordService;
use SmolCms\Service\DB\UserService;
use SmolCms\TestUtils\Attributes\Mock;
use SmolCms\TestUtils\SimpleTestCase;

class PasswordServiceTest extends SimpleTestCase
{
    private PasswordService $service;
    #[Mock(UserService::class)]
    private UserService|MockObject $userService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new PasswordService($this->userService);
    }

    public function testHash_ReturnsVerifiableHash(): void
    {
        $this->userService
            ->expects($this->never())
            ->method('saveOrUpdate')
            ->seal();

        $password = 'test-password';
        $hash = $this->service->hash($password);

        $this->assertNotEmpty($hash);
        $this->assertTrue(password_verify($password, $hash));
    }

    public function testVerifyAndRehashIfNeeded_ReturnsTrueForCorrectPassword(): void
    {
        $this->userService
            ->expects($this->never())
            ->method('saveOrUpdate')
            ->seal();

        $password = 'correct-password';
        $hash = password_hash($password, PASSWORD_DEFAULT);

        /** @var UserEntity|MockObject $user */
        $user = $this->createStub(UserEntity::class);
        $user
            ->method('getPassword')
            ->willReturn($hash);

        $result = $this->service->verifyAndRehashIfNeeded($password, $user);

        $this->assertTrue($result);
    }

    public function testVerifyAndRehashIfNeeded_ReturnsFalseForWrongPassword(): void
    {
        $this->userService
            ->expects($this->never())
            ->method('saveOrUpdate')
            ->seal();

        $hash = password_hash('correct-password', PASSWORD_DEFAULT);

        /** @var UserEntity|MockObject $user */
        $user = $this->createStub(UserEntity::class);
        $user
            ->method('getPassword')
            ->willReturn($hash)
            ->seal();

        $result = $this->service->verifyAndRehashIfNeeded('wrong-password', $user);

        $this->assertFalse($result);
    }

    public function testVerifyAndRehashIfNeeded_RehashesWhenNeeded(): void
    {
        $password = 'test-password';
        $weakHash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 4]);

        /** @var UserEntity|MockObject $user */
        $user = $this->createMock(UserEntity::class);
        $user
            ->method('getPassword')
            ->willReturn($weakHash);
        $user
            ->expects($this->once())
            ->method('setPassword')
            ->seal();

        $this->userService
            ->expects($this->once())
            ->method('saveOrUpdate')
            ->with($user)
            ->seal();

        $result = $this->service->verifyAndRehashIfNeeded($password, $user);

        $this->assertTrue($result);
    }
}
