<?php

declare(strict_types=1);

namespace SmolCms\Test\Service\DB;

use DateTime;
use PDO;
use SmolCms\Data\Constant\AccessLevel;
use SmolCms\Data\Persistence\UserEntity;
use SmolCms\Service\DB\ApiKeyService;
use SmolCms\Service\DB\UserService;
use SmolCms\TestUtils\Attributes\Autowire;
use SmolCms\TestUtils\FunctionalTestCase;

class ApiKeyServiceTest extends FunctionalTestCase
{
    #[Autowire]
    private ApiKeyService $apiKeyService;

    #[Autowire]
    private PDO $pdo;

    #[Autowire]
    private UserService $userService;

    public function testGenerateStoresOnlyAHashAndKeyLookupReturnsFreshOwnerData(): void
    {
        $user = $this->createUser('api-key-owner-' . bin2hex(random_bytes(4)), 'API Key Owner', AccessLevel::WIZARD);
        $userId = $user->getId();

        $apiKey = $this->apiKeyService->generateForUser($userId);

        self::assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $apiKey);
        $statement = $this->pdo->prepare('SELECT key_hash FROM api_key WHERE user_id = :user_id');
        $statement->execute(['user_id' => $userId]);
        $storedHash = $statement->fetchColumn();
        self::assertSame(hash('sha256', $apiKey), $storedHash);
        self::assertNotSame($apiKey, $storedHash);
        self::assertSame(
            [
                'id' => $userId,
                'displayName' => 'API Key Owner',
                'accessLevel' => AccessLevel::WIZARD,
            ],
            self::userDataSummary($this->apiKeyService->findUserDataByApiKey($apiKey)),
        );
    }

    public function testRegeneratingReplacesTheExistingKeyForAUser(): void
    {
        $user = $this->createUser('api-key-rotate-' . bin2hex(random_bytes(4)), 'Key Rotation User');
        $userId = $user->getId();

        $oldKey = $this->apiKeyService->generateForUser($userId);
        $newKey = $this->apiKeyService->generateForUser($userId);

        self::assertNotSame($oldKey, $newKey);
        self::assertNull($this->apiKeyService->findUserDataByApiKey($oldKey));
        self::assertSame($userId, $this->apiKeyService->findUserDataByApiKey($newKey)?->id);
        $count = $this->pdo->query('SELECT COUNT(*) FROM api_key WHERE user_id = ' . $userId)->fetchColumn();
        self::assertSame(1, (int)$count);
    }

    public function testLookupReturnsNullForUnknownKey(): void
    {
        self::assertNull($this->apiKeyService->findUserDataByApiKey(str_repeat('0', 64)));
    }

    public function testLookupRetainsOwnerIdForAnOrphanedKeySoAuthorizationCanFailClosed(): void
    {
        $user = $this->createUser('api-key-orphan-' . bin2hex(random_bytes(4)), 'Orphaned Key User');
        $userId = $user->getId();
        $apiKey = $this->apiKeyService->generateForUser($userId);
        $deleteUser = $this->pdo->prepare('DELETE FROM user WHERE id = :id');
        $deleteUser->execute(['id' => $userId]);

        $userData = $this->apiKeyService->findUserDataByApiKey($apiKey);

        self::assertNotNull($userData);
        self::assertSame($userId, $userData->id);
        self::assertSame('', $userData->displayName);
        self::assertSame(AccessLevel::NOVICE, $userData->accessLevel);
    }

    private function createUser(
        string $loginName,
        string $displayName,
        AccessLevel $accessLevel = AccessLevel::NOVICE,
    ): UserEntity
    {
        $user = new UserEntity(
            id: null,
            loginName: $loginName,
            password: 'hashed-password',
            displayName: $displayName,
            state: 'active',
            registerDate: new DateTime('2026-10-20 12:00:00'),
            lastLoginDate: null,
            accessLevel: $accessLevel,
        );
        $this->userService->saveAsNew($user);
        return $user;
    }

    /** @return array{id: int, displayName: string, accessLevel: AccessLevel}|null */
    private static function userDataSummary(?\SmolCms\Data\DTO\SessionUserData $userData): ?array
    {
        if ($userData === null) {
            return null;
        }
        return [
            'id' => $userData->id,
            'displayName' => $userData->displayName,
            'accessLevel' => $userData->accessLevel,
        ];
    }
}
