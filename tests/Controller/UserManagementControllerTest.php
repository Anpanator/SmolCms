<?php

declare(strict_types=1);

namespace SmolCms\Test\Controller;

use DateTime;
use SmolCms\Data\Business\Url;
use SmolCms\Data\Constant\AccessLevel;
use SmolCms\Data\Constant\HttpMethod;
use SmolCms\Data\Constant\HttpStatus;
use SmolCms\Data\Persistence\UserEntity;
use SmolCms\Data\Request\Request;
use SmolCms\Data\Response\Response;
use SmolCms\Service\DB\UserService;
use SmolCms\Service\Core\Session\SessionService;
use SmolCms\TestUtils\Attributes\Autowire;
use SmolCms\TestUtils\FunctionalTestCase;
use SmolCms\TestUtils\LoginWithAccessLevel;

class UserManagementControllerTest extends FunctionalTestCase
{
    use LoginWithAccessLevel;

    #[Autowire]
    private UserService $userService;

    #[Autowire]
    private SessionService $sessionService;

    public function testUserSearchRequiresModFiltersAndPaginatesThirtyPerPage(): void
    {
        self::assertSame(HttpStatus::UNAUTHORIZED, $this->simulateUsersRequest()->getStatus());
        $this->loginUser('users-novice-' . bin2hex(random_bytes(4)), 'Users Novice');
        self::assertSame(HttpStatus::FORBIDDEN, $this->simulateUsersRequest()->getStatus());

        $token = bin2hex(random_bytes(5));
        $target = $this->createUser('users-search-login-' . $token, 'Users Search Display ' . $token, AccessLevel::FELLOW);
        $this->loginUserWithAccessLevel(
            'users-mod-' . bin2hex(random_bytes(4)),
            'Users Mod',
            AccessLevel::MOD,
        );

        foreach ([(string)$target->getId(), 'users-search-login-' . $token, 'Users Search Display ' . $token] as $query) {
            $response = $this->simulateUsersRequest(['q' => $query]);
            self::assertSame(HttpStatus::OK, $response->getStatus());
            $body = json_decode((string)$response->getContent(), true, JSON_THROW_ON_ERROR);
            $matchingUsers = array_values(array_filter(
                $body['users'],
                static fn(array $user): bool => $user['id'] === $target->getId(),
            ));
            self::assertCount(1, $matchingUsers);
            self::assertSame([
                'id' => $target->getId(),
                'loginName' => 'users-search-login-' . $token,
                'displayName' => 'Users Search Display ' . $token,
                'accessLevel' => AccessLevel::FELLOW->value,
                'registerDate' => '2026-10-19 12:00:00',
            ], $matchingUsers[0]);
        }

        self::assertSame(HttpStatus::BAD_REQUEST, $this->simulateUsersRequest(['page' => '0'])->getStatus());
        self::assertSame(HttpStatus::BAD_REQUEST, $this->simulateUsersRequest(['q' => ['invalid']])->getStatus());

        $pageToken = bin2hex(random_bytes(5));
        for ($index = 0; $index < 31; $index++) {
            $this->createUser(
                'users-page-' . $pageToken . '-' . sprintf('%02d', $index),
                'Users Page ' . $pageToken . ' ' . $index,
                AccessLevel::NOVICE,
            );
        }
        $firstResponse = $this->simulateUsersRequest(['q' => $pageToken]);
        $firstPage = json_decode((string)$firstResponse->getContent(), true, JSON_THROW_ON_ERROR);
        self::assertSame(31, $firstPage['total']);
        self::assertSame(30, $firstPage['perPage']);
        self::assertCount(30, $firstPage['users']);
        self::assertSame(1, $firstPage['page']);

        $secondResponse = $this->simulateUsersRequest(['page' => '2', 'q' => $pageToken]);
        $secondPage = json_decode((string)$secondResponse->getContent(), true, JSON_THROW_ON_ERROR);
        self::assertSame(2, $secondPage['page']);
        self::assertSame(31, $secondPage['total']);
        self::assertCount(1, $secondPage['users']);
    }

    public function testUserUpdateEnforcesAccessGuardAndOnlyChangesDisplayNameAndLevel(): void
    {
        self::assertSame(
            HttpStatus::UNAUTHORIZED,
            $this->simulateUserUpdate(1, ['displayName' => 'Changed', 'accessLevel' => AccessLevel::NOVICE->value])->getStatus(),
        );
        $this->loginUser('users-update-novice-' . bin2hex(random_bytes(4)), 'Users Update Novice');
        self::assertSame(
            HttpStatus::FORBIDDEN,
            $this->simulateUserUpdate(1, ['displayName' => 'Changed', 'accessLevel' => AccessLevel::NOVICE->value])->getStatus(),
        );

        $admin = $this->createUser('users-admin-' . bin2hex(random_bytes(4)), 'Protected Admin', AccessLevel::ADMIN);
        $sameLevelMod = $this->createUser('users-target-mod-' . bin2hex(random_bytes(4)), 'Target Mod', AccessLevel::MOD);
        $fellow = $this->createUser('users-target-fellow-' . bin2hex(random_bytes(4)), 'Target Fellow', AccessLevel::FELLOW);
        $novice = $this->createUser('users-target-novice-' . bin2hex(random_bytes(4)), 'Target Novice', AccessLevel::NOVICE);
        $actorLoginName = 'users-update-mod-' . bin2hex(random_bytes(4));
        $this->loginUserWithAccessLevel(
            $actorLoginName,
            'Users Update Mod',
            AccessLevel::MOD,
        );

        self::assertSame(
            HttpStatus::FORBIDDEN,
            $this->simulateUserUpdate($admin->getId(), [
                'displayName' => 'Should Not Change Admin',
                'accessLevel' => AccessLevel::NOVICE->value,
            ])->getStatus(),
        );
        self::assertSame('Protected Admin', $this->userService->findOneById($admin->getId())->getDisplayName());

        self::assertSame(
            HttpStatus::FORBIDDEN,
            $this->simulateUserUpdate($sameLevelMod->getId(), [
                'displayName' => 'Should Not Change Same-Level User',
                'accessLevel' => AccessLevel::FELLOW->value,
            ])->getStatus(),
        );
        self::assertSame('Target Mod', $this->userService->findOneById($sameLevelMod->getId())->getDisplayName());
        self::assertSame(AccessLevel::MOD, $this->userService->findOneById($sameLevelMod->getId())->getAccessLevel());

        $fellowResponse = $this->simulateUserUpdate($fellow->getId(), [
            'displayName' => 'Updated Fellow Display',
            'accessLevel' => AccessLevel::NOVICE->value,
        ]);
        self::assertSame(HttpStatus::OK, $fellowResponse->getStatus());
        self::assertSame(
            ['user' => [
                'id' => $fellow->getId(),
                'loginName' => $fellow->getLoginName(),
                'displayName' => 'Updated Fellow Display',
                'accessLevel' => AccessLevel::NOVICE->value,
            ]],
            json_decode((string)$fellowResponse->getContent(), true, JSON_THROW_ON_ERROR),
        );
        self::assertSame(AccessLevel::NOVICE, $this->userService->findOneById($fellow->getId())->getAccessLevel());

        $noviceResponse = $this->simulateUserUpdate($novice->getId(), [
            'displayName' => 'Updated Novice Display',
            'accessLevel' => AccessLevel::FELLOW->value,
        ]);
        self::assertSame(HttpStatus::OK, $noviceResponse->getStatus());
        self::assertSame(AccessLevel::FELLOW, $this->userService->findOneById($novice->getId())->getAccessLevel());

        foreach ([AccessLevel::MOD, AccessLevel::ADMIN] as $forbiddenLevel) {
            self::assertSame(
                HttpStatus::FORBIDDEN,
                $this->simulateUserUpdate($novice->getId(), [
                    'displayName' => 'Escalation Attempt',
                    'accessLevel' => $forbiddenLevel->value,
                ])->getStatus(),
            );
            $unchangedNovice = $this->userService->findOneById($novice->getId());
            self::assertSame('Updated Novice Display', $unchangedNovice->getDisplayName());
            self::assertSame(AccessLevel::FELLOW, $unchangedNovice->getAccessLevel());
        }

        $actor = $this->userService->findOneByLoginName($actorLoginName);
        self::assertNotNull($actor);
        foreach ([AccessLevel::FELLOW, AccessLevel::ADMIN] as $requestedActorLevel) {
            self::assertSame(
                HttpStatus::FORBIDDEN,
                $this->simulateUserUpdate($actor->getId(), [
                    'displayName' => 'Self Update Attempt',
                    'accessLevel' => $requestedActorLevel->value,
                ])->getStatus(),
            );
            $unchangedActor = $this->userService->findOneById($actor->getId());
            self::assertSame('Users Update Mod', $unchangedActor->getDisplayName());
            self::assertSame(AccessLevel::MOD, $unchangedActor->getAccessLevel());
        }

        self::assertSame(
            HttpStatus::NOT_FOUND,
            $this->simulateUserUpdate(999999, [
                'displayName' => 'Missing',
                'accessLevel' => AccessLevel::NOVICE->value,
            ])->getStatus(),
        );
        self::assertSame(
            HttpStatus::BAD_REQUEST,
            $this->simulateUserUpdate('bad', [
                'displayName' => 'Missing',
                'accessLevel' => AccessLevel::NOVICE->value,
            ])->getStatus(),
        );
        self::assertSame(
            HttpStatus::BAD_REQUEST,
            $this->simulateUserUpdate($novice->getId(), [
                'displayName' => '',
                'accessLevel' => AccessLevel::NOVICE->value,
            ])->getStatus(),
        );
        self::assertSame(
            HttpStatus::BAD_REQUEST,
            $this->simulateUserUpdate($novice->getId(), [
                'displayName' => 'Invalid Level',
                'accessLevel' => 123,
            ])->getStatus(),
        );
    }

    public function testUserUpdateRequiresIdDisplayNameAndAccessLevel(): void
    {
        $this->loginUserWithAccessLevel(
            'users-required-fields-mod-' . bin2hex(random_bytes(4)),
            'Users Required Fields Mod',
            AccessLevel::MOD,
        );
        $target = $this->createUser(
            'users-required-fields-target-' . bin2hex(random_bytes(4)),
            'Required Fields Target',
            AccessLevel::NOVICE,
        );
        $validFields = [
            'displayName' => 'Updated Required Fields Target',
            'accessLevel' => AccessLevel::NOVICE->value,
        ];

        $withoutQuery = $this->simulateUserUpdate(null, $validFields);
        self::assertSame(HttpStatus::BAD_REQUEST, $withoutQuery->getStatus());

        $withoutIdOrBody = $this->simulateUserUpdate(null, []);
        self::assertSame(HttpStatus::BAD_REQUEST, $withoutIdOrBody->getStatus());

        $withoutDisplayName = $this->simulateUserUpdate($target->getId(), [
            'accessLevel' => AccessLevel::NOVICE->value,
        ]);
        self::assertSame(HttpStatus::BAD_REQUEST, $withoutDisplayName->getStatus());

        $withoutAccessLevel = $this->simulateUserUpdate($target->getId(), [
            'displayName' => 'Missing Access Level',
        ]);
        self::assertSame(HttpStatus::BAD_REQUEST, $withoutAccessLevel->getStatus());
    }

    public function testProtectedRequestsUseDatabaseRoleAndRefreshSessionRoleAfterDemotion(): void
    {
        $loginName = 'users-stale-session-' . bin2hex(random_bytes(4));
        $this->loginUserWithAccessLevel($loginName, 'Stale Session Mod', AccessLevel::MOD);
        $user = $this->userService->findOneByLoginName($loginName);
        self::assertNotNull($user);

        $user->setAccessLevel(AccessLevel::FELLOW);
        $this->userService->update($user);

        self::assertSame(HttpStatus::FORBIDDEN, $this->simulateUsersRequest()->getStatus());

        $sessionUser = $this->sessionService->getUserData();
        self::assertNotNull($sessionUser);
        self::assertSame(AccessLevel::FELLOW, $sessionUser->accessLevel);
        self::assertSame('Stale Session Mod', $sessionUser->displayName);
    }

    public function testUserUpdateCanChangeLoginNameAndChecksUniquenessAndValidation(): void
    {
        $token = bin2hex(random_bytes(5));
        $target = $this->createUser('users-login-target-' . $token, 'Login Target', AccessLevel::NOVICE);
        $other = $this->createUser('users-login-other-' . $token, 'Login Other', AccessLevel::NOVICE);
        $this->loginUserWithAccessLevel(
            'users-login-mod-' . $token,
            'Users Login Mod',
            AccessLevel::MOD,
        );

        $updatedLoginName = 'users-login-updated-' . $token;
        $updatedResponse = $this->simulateUserUpdate($target->getId(), [
            'loginName' => $updatedLoginName,
            'displayName' => 'Updated Login Target',
            'accessLevel' => AccessLevel::NOVICE->value,
        ]);
        self::assertSame(HttpStatus::OK, $updatedResponse->getStatus());
        self::assertSame([
            'user' => [
                'id' => $target->getId(),
                'loginName' => $updatedLoginName,
                'displayName' => 'Updated Login Target',
                'accessLevel' => AccessLevel::NOVICE->value,
            ],
        ], json_decode((string)$updatedResponse->getContent(), true, JSON_THROW_ON_ERROR));
        self::assertSame($updatedLoginName, $this->userService->findOneById($target->getId())->getLoginName());

        $unchangedResponse = $this->simulateUserUpdate($target->getId(), [
            'displayName' => 'Login Name Unchanged',
            'accessLevel' => AccessLevel::NOVICE->value,
        ]);
        self::assertSame(HttpStatus::OK, $unchangedResponse->getStatus());
        self::assertSame(
            $updatedLoginName,
            $this->userService->findOneById($target->getId())->getLoginName(),
            'an absent loginName should leave the current login name unchanged',
        );

        $nullResponse = $this->simulateUserUpdate($target->getId(), [
            'loginName' => null,
            'displayName' => 'Login Name Still Unchanged',
            'accessLevel' => AccessLevel::NOVICE->value,
        ]);
        self::assertSame(HttpStatus::OK, $nullResponse->getStatus());
        self::assertSame($updatedLoginName, $this->userService->findOneById($target->getId())->getLoginName());

        $duplicateResponse = $this->simulateUserUpdate($target->getId(), [
            'loginName' => $other->getLoginName(),
            'displayName' => 'Should Not Be Saved',
            'accessLevel' => AccessLevel::NOVICE->value,
        ]);
        self::assertSame(HttpStatus::CONFLICT, $duplicateResponse->getStatus());
        $afterDuplicate = $this->userService->findOneById($target->getId());
        self::assertSame($updatedLoginName, $afterDuplicate->getLoginName());
        self::assertSame('Login Name Still Unchanged', $afterDuplicate->getDisplayName());

        foreach (['', str_repeat('a', 256)] as $invalidLoginName) {
            self::assertSame(
                HttpStatus::BAD_REQUEST,
                $this->simulateUserUpdate($target->getId(), [
                    'loginName' => $invalidLoginName,
                    'displayName' => 'Valid Display',
                    'accessLevel' => AccessLevel::NOVICE->value,
                ])->getStatus(),
            );
        }
    }

    private function createUser(string $loginName, string $displayName, AccessLevel $accessLevel): UserEntity
    {
        $user = new UserEntity(
            id: null,
            loginName: $loginName,
            password: $this->hashTestPassword('secure-password-123'),
            displayName: $displayName,
            state: 'active',
            registerDate: new DateTime('2026-10-19 12:00:00'),
            lastLoginDate: null,
            accessLevel: $accessLevel,
        );
        $this->userService->saveAsNew($user);
        return $user;
    }

    private function simulateUsersRequest(?array $params = null): Response
    {
        return $this->simulateRequest(new Request(
            url: new Url(protocol: 'https', host: 'localhost', path: '/api/users'),
            method: HttpMethod::GET,
            getParams: $params,
        ));
    }

    private function simulateUserUpdate(int|string|null $id, array $postParams): Response
    {
        return $this->simulateRequest(new Request(
            url: new Url(protocol: 'https', host: 'localhost', path: '/api/users/update'),
            method: HttpMethod::POST,
            getParams: $id === null ? null : ['id' => (string)$id],
            postParams: $postParams,
        ));
    }
}
