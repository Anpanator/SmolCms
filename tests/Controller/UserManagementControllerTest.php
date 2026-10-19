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
use SmolCms\TestUtils\Attributes\Autowire;
use SmolCms\TestUtils\FunctionalTestCase;
use SmolCms\TestUtils\LoginWithAccessLevel;

class UserManagementControllerTest extends FunctionalTestCase
{
    use LoginWithAccessLevel;

    #[Autowire]
    private UserService $userService;

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
        $mod = $this->createUser('users-target-mod-' . bin2hex(random_bytes(4)), 'Target Mod', AccessLevel::MOD);
        $novice = $this->createUser('users-target-novice-' . bin2hex(random_bytes(4)), 'Target Novice', AccessLevel::NOVICE);
        $this->loginUserWithAccessLevel(
            'users-update-mod-' . bin2hex(random_bytes(4)),
            'Users Update Mod',
            AccessLevel::MOD,
        );

        self::assertSame(
            HttpStatus::FORBIDDEN,
            $this->simulateUserUpdate($admin->getId(), [
                'displayName' => 'Should Not Change',
                'accessLevel' => AccessLevel::MOD->value,
            ])->getStatus(),
        );
        self::assertSame('Protected Admin', $this->userService->findOneById($admin->getId())->getDisplayName());

        $modResponse = $this->simulateUserUpdate($mod->getId(), [
            'displayName' => 'Updated Mod Display',
            'accessLevel' => AccessLevel::FELLOW->value,
        ]);
        self::assertSame(HttpStatus::OK, $modResponse->getStatus());
        self::assertSame(
            ['user' => [
                'id' => $mod->getId(),
                'displayName' => 'Updated Mod Display',
                'accessLevel' => AccessLevel::FELLOW->value,
            ]],
            json_decode((string)$modResponse->getContent(), true, JSON_THROW_ON_ERROR),
        );
        self::assertSame(AccessLevel::FELLOW, $this->userService->findOneById($mod->getId())->getAccessLevel());

        $noviceResponse = $this->simulateUserUpdate($novice->getId(), [
            'displayName' => 'Updated Novice Display',
            'accessLevel' => AccessLevel::FELLOW->value,
        ]);
        self::assertSame(HttpStatus::OK, $noviceResponse->getStatus());
        self::assertSame(AccessLevel::FELLOW, $this->userService->findOneById($novice->getId())->getAccessLevel());

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

    private function createUser(string $loginName, string $displayName, AccessLevel $accessLevel): UserEntity
    {
        $user = new UserEntity(
            id: null,
            loginName: $loginName,
            password: password_hash('secure-password-123', PASSWORD_DEFAULT),
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

    private function simulateUserUpdate(int|string $id, array $postParams): Response
    {
        return $this->simulateRequest(new Request(
            url: new Url(protocol: 'https', host: 'localhost', path: '/api/users/update'),
            method: HttpMethod::POST,
            getParams: ['id' => (string)$id],
            postParams: $postParams,
        ));
    }
}
