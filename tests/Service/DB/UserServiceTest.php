<?php
declare(strict_types=1);

namespace SmolCms\Test\Service\DB;

use DateTime;
use SmolCms\Data\Constant\AccessLevel;
use SmolCms\Data\Persistence\UserEntity;
use SmolCms\Service\DB\UserService;
use SmolCms\TestUtils\Attributes\Autowire;
use SmolCms\TestUtils\FunctionalTestCase;

class UserServiceTest extends FunctionalTestCase
{
    #[Autowire]
    private UserService $userService;

    public function testFindOneByLoginNameAndId_returnsSavedUser(): void
    {
        $registerDate = new DateTime('2024-02-03 04:05:06');
        $lastLoginDate = new DateTime('2024-03-04 05:06:07');
        $user = new UserEntity(
            id: null,
            loginName: 'user-service-found',
            password: 'hashed-password',
            displayName: 'Found User',
            state: 'active',
            registerDate: $registerDate,
            lastLoginDate: $lastLoginDate,
            accessLevel: AccessLevel::FELLOW,
        );

        $this->userService->saveAsNew($user);

        $resultByLoginName = $this->userService->findOneByLoginName('user-service-found');
        $resultById = $this->userService->findOneById($user->getId());

        self::assertNotNull($resultByLoginName);
        self::assertNotNull($resultById);
        self::assertSame($user->getId(), $resultByLoginName->getId());
        self::assertSame($user->getId(), $resultById->getId());
        self::assertSame('Found User', $resultByLoginName->getDisplayName());
        self::assertSame('2024-02-03 04:05:06', $resultByLoginName->getRegisterDate()->format('Y-m-d H:i:s'));
        self::assertSame('2024-03-04 05:06:07', $resultByLoginName->getLastLoginDate()?->format('Y-m-d H:i:s'));
        self::assertSame(AccessLevel::FELLOW, $resultByLoginName->getAccessLevel());
    }

    public function testFindOneByLoginNameAndId_returnsNullWhenUserDoesNotExist(): void
    {
        self::assertNull($this->userService->findOneByLoginName('user-service-missing'));
        self::assertNull($this->userService->findOneById(PHP_INT_MAX));
    }

    public function testSaveOrUpdate_updatesExistingUser(): void
    {
        $user = new UserEntity(
            id: null,
            loginName: 'user-service-update',
            password: 'hashed-password',
            displayName: 'Before Update',
            state: 'active',
            registerDate: new DateTime('2024-01-01 00:00:00'),
            lastLoginDate: null,
            accessLevel: AccessLevel::NOVICE,
        );
        $this->userService->saveAsNew($user);
        $user->setDisplayName('After Update');

        $this->userService->saveOrUpdate($user);

        $savedUser = $this->userService->findOneById($user->getId());
        self::assertNotNull($savedUser);
        self::assertSame('After Update', $savedUser->getDisplayName());
    }
}
