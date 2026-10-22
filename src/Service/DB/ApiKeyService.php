<?php

declare(strict_types=1);

namespace SmolCms\Service\DB;

use DateTime;
use PDO;
use SensitiveParameter;
use SmolCms\Data\Constant\AccessLevel;
use SmolCms\Data\DTO\SessionUserData;
use SmolCms\Data\Persistence\ApiKeyEntity;
use SmolCms\Service\Core\CaseConverter;

readonly class ApiKeyService extends EntityService
{
    public function __construct(
        PDO $pdo,
        CaseConverter $caseConverter,
        QueryBuilder $queryBuilder,
        EntityAttributeProcessor $entityAttributeProcessor,
        private UserService $userService,
    )
    {
        parent::__construct($pdo, $caseConverter, $queryBuilder, $entityAttributeProcessor);
    }

    /** Generates and stores the hash of a new API key, replacing any existing key for the user. */
    public function generateForUser(int $userId): string
    {
        $apiKey = bin2hex(random_bytes(32));

        $this->withinTransaction(function () use ($userId, $apiKey): void {
            $this->deleteForUser($userId);
            $this->saveAsNew(new ApiKeyEntity(
                id: null,
                userId: $userId,
                keyHash: hash('sha256', $apiKey),
                created: new DateTime(),
            ));
        });

        return $apiKey;
    }

    public function hasForUser(int $userId): bool
    {
        $criteria = new QueryCriteria();
        $criteria->select(ApiKeyEntity::class)
            ->andWhere('user_id = :user_id')
            ->withParameters(['user_id' => $userId]);
        return $this->countResults($criteria) > 0;
    }

    public function deleteForUser(int $userId): bool
    {
        return $this->withinTransaction(function () use ($userId): bool {
            $criteria = new QueryCriteria();
            $criteria->delete(ApiKeyEntity::class)
                ->andWhere('user_id = :user_id')
                ->withParameters(['user_id' => $userId]);
            $statement = $this->prepare($criteria);
            $statement->execute();
            return $statement->rowCount() > 0;
        });
    }

    public function findUserDataByApiKey(#[SensitiveParameter] string $apiKey): ?SessionUserData
    {
        $criteria = new QueryCriteria();
        $criteria->select(ApiKeyEntity::class)
            ->andWhere('key_hash = :key_hash')
            ->withParameters(['key_hash' => hash('sha256', $apiKey)])
            ->maxResults(1);
        $apiKeyEntity = ($this->execute($criteria) ?? [])[0] ?? null;
        if ($apiKeyEntity === null) {
            return null;
        }

        $user = $this->userService->findOneById($apiKeyEntity->getUserId());

        // Keep an orphaned key's owner ID so authorization can return 403 instead of treating it as invalid.
        if ($user === null) {
            return new SessionUserData(
                id: $apiKeyEntity->getUserId(),
                displayName: '',
                accessLevel: AccessLevel::NOVICE,
            );
        }

        return new SessionUserData(
            id: (int)$user->getId(),
            displayName: $user->getDisplayName(),
            accessLevel: $user->getAccessLevel(),
        );
    }
}
