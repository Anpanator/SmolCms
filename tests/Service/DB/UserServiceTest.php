<?php
declare(strict_types=1);

namespace SmolCms\Test\Service\DB;

use DateTime;
use PDO;
use SmolCms\Data\Constant\AccessLevel;
use SmolCms\Data\Persistence\UserEntity;
use SmolCms\Exception\UserUpdateForbiddenException;
use SmolCms\Service\DB\UserService;
use SmolCms\TestUtils\Attributes\Autowire;
use SmolCms\TestUtils\FunctionalTestCase;

class UserServiceTest extends FunctionalTestCase
{
    #[Autowire]
    private UserService $userService;

    #[Autowire]
    private PDO $pdo;

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

    public function testUpdateUserRejectsGrantingAnAccessLevelAtOrAboveTheActor(): void
    {
        $actor = new UserEntity(
            id: null,
            loginName: 'user-service-actor',
            password: 'hashed-password',
            displayName: 'Moderator',
            state: 'active',
            registerDate: new DateTime('2026-10-20 12:00:00'),
            lastLoginDate: null,
            accessLevel: AccessLevel::MOD,
        );
        $target = new UserEntity(
            id: null,
            loginName: 'user-service-target',
            password: 'hashed-password',
            displayName: 'Novice',
            state: 'active',
            registerDate: new DateTime('2026-10-20 12:00:00'),
            lastLoginDate: null,
            accessLevel: AccessLevel::NOVICE,
        );
        $this->userService->saveAsNew($actor);
        $this->userService->saveAsNew($target);

        try {
            $this->userService->updateUser(
                $target->getId(),
                'Escalated Novice',
                AccessLevel::ADMIN,
                $actor->getId(),
            );
            self::fail('A moderator must not grant an admin role.');
        } catch (UserUpdateForbiddenException) {
            $reloaded = $this->userService->findOneById($target->getId());
            self::assertNotNull($reloaded);
            self::assertSame('Novice', $reloaded->getDisplayName());
            self::assertSame(AccessLevel::NOVICE, $reloaded->getAccessLevel());
        }
    }

    public function testSearchUsersEscapesLikeMetacharactersLiterally(): void
    {
        $marker = 'user-search-' . bin2hex(random_bytes(4)) . '-';
        $cases = [
            'percent' => [$marker . '%tail', $marker . 'Xtail'],
            'underscore' => [$marker . '_tail', $marker . 'Xtail'],
            'backslash' => [$marker . '\\tail', $marker . 'tail'],
        ];
        $createdDecoys = [];

        foreach ($cases as $label => [$literalQuery, $wildcardDecoy]) {
            $matchingUser = $this->createUser('login-' . $literalQuery, 'Matching ' . $label);
            if (!isset($createdDecoys[$wildcardDecoy])) {
                $this->createUser('login-' . $wildcardDecoy, 'Decoy ' . $label);
                $createdDecoys[$wildcardDecoy] = true;
            }

            $result = $this->userService->searchUsers(1, $literalQuery);

            self::assertSame(1, $result['total'], "The $label search metacharacter must be literal.");
            self::assertSame([$matchingUser->getId()], array_column($result['users'], 'id'));
        }
    }

    public function testSearchUsersNumericQueriesMatchNamesAndIdsAndHandleOverflow(): void
    {
        $numericName = '83194627';
        $loginMatch = $this->createUser('numeric-login-' . $numericName, 'Login Match');
        $displayMatch = $this->createUser('numeric-display-match', 'Display Match ' . $numericName);

        $nameMatches = $this->userService->searchUsers(1, $numericName);
        self::assertSame(2, $nameMatches['total']);
        self::assertEqualsCanonicalizing(
            [$loginMatch->getId(), $displayMatch->getId()],
            array_column($nameMatches['users'], 'id'),
        );

        $idMatches = $this->userService->searchUsers(1, (string)$loginMatch->getId());
        self::assertContains($loginMatch->getId(), array_column($idMatches['users'], 'id'));
        $leadingZeroMatches = $this->userService->searchUsers(1, '000000' . $loginMatch->getId());
        self::assertContains($loginMatch->getId(), array_column($leadingZeroMatches['users'], 'id'));

        $zeroName = $this->createUser('zero-query-000', 'Zero Query');
        $zeroMatches = $this->userService->searchUsers(1, '000');
        self::assertContains($zeroName->getId(), array_column($zeroMatches['users'], 'id'));

        $overflow = str_repeat('9', strlen((string)PHP_INT_MAX) + 5);
        $overflowUser = $this->createUser('numeric-overflow-' . $overflow, 'Overflow User');
        $overflowMatches = $this->userService->searchUsers(1, $overflow);
        self::assertSame([$overflowUser->getId()], array_column($overflowMatches['users'], 'id'));
    }

    public function testSearchUsersEmptyQueryReturnsUnfilteredPageMetadata(): void
    {
        $this->createUser('empty-search-' . bin2hex(random_bytes(4)), 'Empty Search');
        $expectedTotal = (int)$this->pdo->query('SELECT COUNT(*) FROM user')->fetchColumn();

        $result = $this->userService->searchUsers(1, '');

        self::assertSame($expectedTotal, $result['total']);
        self::assertSame(1, $result['page']);
        self::assertSame(30, $result['perPage']);
        self::assertCount(min(30, $expectedTotal), $result['users']);
    }

    private function createUser(string $loginName, string $displayName): UserEntity
    {
        $user = new UserEntity(
            id: null,
            loginName: $loginName,
            password: 'hashed-password',
            displayName: $displayName,
            state: 'active',
            registerDate: new DateTime('2026-10-20 12:00:00'),
            lastLoginDate: null,
            accessLevel: AccessLevel::NOVICE,
        );
        $this->userService->saveAsNew($user);
        return $user;
    }
}
