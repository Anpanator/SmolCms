<?php

declare(strict_types=1);

namespace SmolCms\Service\DB;

use PDO;
use SensitiveParameter;
use SmolCms\Data\Constant\AccessLevel;
use SmolCms\Data\DTO\SessionUserData;
use Throwable;

readonly class ApiKeyService
{
    public function __construct(private PDO $pdo)
    {
    }

    /** Generates and stores the hash of a new API key, replacing any existing key for the user. */
    public function generateForUser(int $userId): string
    {
        $apiKey = bin2hex(random_bytes(32));
        $keyHash = hash('sha256', $apiKey);

        $this->withinTransaction(function () use ($userId, $keyHash): void {
            $delete = $this->pdo->prepare('DELETE FROM api_key WHERE user_id = :user_id');
            $delete->bindValue(':user_id', $userId, PDO::PARAM_INT);
            $delete->execute();

            $insert = $this->pdo->prepare('INSERT INTO api_key (user_id, key_hash) VALUES (:user_id, :key_hash)');
            $insert->bindValue(':user_id', $userId, PDO::PARAM_INT);
            $insert->bindValue(':key_hash', $keyHash, PDO::PARAM_STR);
            $insert->execute();
        });

        return $apiKey;
    }

    public function hasForUser(int $userId): bool
    {
        $statement = $this->pdo->prepare('SELECT 1 FROM api_key WHERE user_id = :user_id LIMIT 1');
        $statement->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $statement->execute();
        return $statement->fetchColumn() !== false;
    }

    public function deleteForUser(int $userId): bool
    {
        $statement = $this->pdo->prepare('DELETE FROM api_key WHERE user_id = :user_id');
        $statement->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $statement->execute();
        return $statement->rowCount() > 0;
    }

    public function findUserDataByApiKey(#[SensitiveParameter] string $apiKey): ?SessionUserData
    {
        $statement = $this->pdo->prepare(
            'SELECT api_key.user_id, user.display_name, user.access_level '
            . 'FROM api_key LEFT JOIN user ON user.id = api_key.user_id '
            . 'WHERE api_key.key_hash = :key_hash LIMIT 1'
        );
        $statement->bindValue(':key_hash', hash('sha256', $apiKey), PDO::PARAM_STR);
        $statement->execute();
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            return null;
        }

        // Keep an orphaned key's owner ID so authorization can return 403 instead of treating it as invalid.
        return new SessionUserData(
            id: (int)$row['user_id'],
            displayName: $row['display_name'] === null ? '' : (string)$row['display_name'],
            accessLevel: $row['access_level'] === null
                ? AccessLevel::NOVICE
                : AccessLevel::from((int)$row['access_level']),
        );
    }

    /** @param callable(): void $operation */
    private function withinTransaction(callable $operation): void
    {
        $ownsTransaction = !$this->pdo->inTransaction();
        if ($ownsTransaction) {
            $this->pdo->beginTransaction();
        }

        try {
            $operation();
            if ($ownsTransaction) {
                $this->pdo->commit();
            }
        } catch (Throwable $throwable) {
            if ($ownsTransaction && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $throwable;
        }
    }
}
