<?php

declare(strict_types=1);

namespace SmolCms\Service\Tag;

use InvalidArgumentException;
use PDO;

/** Tracks user-driven tag activity and builds each user's quick-tag lists. */
readonly class UserTagUsageService
{
    private const string EXCLUDED_TAG = 'tagme';
    private const int GLOBAL_LIMIT = 20;
    private const int USER_LIMIT = 10;

    public function __construct(private PDO $pdo)
    {
    }

    /** Adds one successful user-driven image/tag association to that user's usage. */
    public function recordTagAdded(int $userId, string $tagName): void
    {
        $tagId = $this->findTrackableTagId($tagName);
        if ($userId < 1 || $tagId === null) {
            return;
        }

        $statement = $this->pdo->prepare(
            'INSERT INTO user_tag_usage (user_id, tag_id, usage_count, last_used_at) '
            . 'VALUES (:user_id, :tag_id, 1, CURRENT_TIMESTAMP) '
            . 'ON CONFLICT(user_id, tag_id) DO UPDATE SET '
            . 'usage_count = user_tag_usage.usage_count + 1, '
            . 'last_used_at = CURRENT_TIMESTAMP'
        );
        $statement->execute(['user_id' => $userId, 'tag_id' => $tagId]);
    }

    /** Records a successful removal, preserving the last time the tag was added. */
    public function recordTagRemoved(int $userId, string $tagName): void
    {
        $tagId = $this->findTrackableTagId($tagName);
        if ($userId < 1 || $tagId === null) {
            return;
        }

        $statement = $this->pdo->prepare(
            'INSERT INTO user_tag_usage (user_id, tag_id, usage_count, last_used_at) '
            . 'VALUES (:user_id, :tag_id, 0, NULL) '
            . 'ON CONFLICT(user_id, tag_id) DO UPDATE SET '
            . 'usage_count = CASE WHEN user_tag_usage.usage_count > 0 '
            . 'THEN user_tag_usage.usage_count - 1 ELSE 0 END'
        );
        $statement->execute(['user_id' => $userId, 'tag_id' => $tagId]);
    }

    /**
     * @return array{
     *     global: list<array{name: string, category: string}>,
     *     userMostUsed: list<array{name: string, category: string}>,
     *     userRecent: list<array{name: string, category: string}>
     * }
     */
    public function getQuickTags(int $userId): array
    {
        $global = $this->fetchTags(
            'SELECT tag.name, category.name AS category '
            . 'FROM tag '
            . 'INNER JOIN tag_category AS category ON category.id = tag.category_id '
            . 'WHERE tag.name <> :excluded_tag COLLATE NOCASE '
            . 'ORDER BY tag.usage_count DESC, tag.name COLLATE NOCASE, tag.name '
            . 'LIMIT ' . self::GLOBAL_LIMIT,
            ['excluded_tag' => self::EXCLUDED_TAG],
        );

        $mostUsedRows = $this->fetchRows(
            'SELECT tag.id AS tag_id, tag.name, category.name AS category '
            . 'FROM user_tag_usage AS user_usage '
            . 'INNER JOIN tag ON tag.id = user_usage.tag_id '
            . 'INNER JOIN tag_category AS category ON category.id = tag.category_id '
            . 'WHERE user_usage.user_id = :user_id '
            . 'AND user_usage.usage_count > 0 '
            . 'AND tag.name <> :excluded_tag COLLATE NOCASE '
            . 'ORDER BY user_usage.usage_count DESC, tag.name COLLATE NOCASE, tag.name '
            . 'LIMIT ' . self::USER_LIMIT,
            ['user_id' => $userId, 'excluded_tag' => self::EXCLUDED_TAG],
        );
        $userMostUsed = self::formatTags($mostUsedRows);
        $excludedTagIds = array_map(static fn(array $row): int => (int)$row['tag_id'], $mostUsedRows);
        $userRecent = $this->findRecentTags($userId, $excludedTagIds);

        return [
            'global' => $global,
            'userMostUsed' => $userMostUsed,
            'userRecent' => $userRecent,
        ];
    }

    private function findTrackableTagId(string $tagName): ?int
    {
        try {
            $normalizedName = TagService::normalizeName($tagName);
        } catch (InvalidArgumentException) {
            return null;
        }
        if ($normalizedName === self::EXCLUDED_TAG) {
            return null;
        }

        $statement = $this->pdo->prepare('SELECT id FROM tag WHERE name = :name COLLATE NOCASE');
        $statement->execute(['name' => $normalizedName]);
        $tagId = $statement->fetchColumn();
        return $tagId === false ? null : (int)$tagId;
    }

    /** @param list<int> $excludedTagIds @return list<array{name: string, category: string}> */
    private function findRecentTags(int $userId, array $excludedTagIds): array
    {
        $parameters = [
            'user_id' => $userId,
            'excluded_tag' => self::EXCLUDED_TAG,
        ];
        $excluded = '';
        if ($excludedTagIds !== []) {
            $placeholders = [];
            foreach (array_values($excludedTagIds) as $index => $tagId) {
                $parameter = 'excluded_tag_' . $index;
                $placeholders[] = ':' . $parameter;
                $parameters[$parameter] = $tagId;
            }
            $excluded = 'AND tag.id NOT IN (' . implode(', ', $placeholders) . ') ';
        }

        return $this->fetchTags(
            'SELECT tag.name, category.name AS category '
            . 'FROM user_tag_usage AS user_usage '
            . 'INNER JOIN tag ON tag.id = user_usage.tag_id '
            . 'INNER JOIN tag_category AS category ON category.id = tag.category_id '
            . 'WHERE user_usage.user_id = :user_id '
            . 'AND user_usage.usage_count > 0 '
            . 'AND user_usage.last_used_at IS NOT NULL '
            . 'AND tag.name <> :excluded_tag COLLATE NOCASE '
            . $excluded
            . 'ORDER BY user_usage.last_used_at DESC, tag.name COLLATE NOCASE, tag.name '
            . 'LIMIT ' . self::USER_LIMIT,
            $parameters,
        );
    }

    /** @param array<string, mixed> $parameters @return list<array<string, mixed>> */
    private function fetchRows(string $query, array $parameters): array
    {
        $statement = $this->pdo->prepare($query);
        $statement->execute($parameters);
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @param array<string, mixed> $parameters @return list<array{name: string, category: string}> */
    private function fetchTags(string $query, array $parameters): array
    {
        return self::formatTags($this->fetchRows($query, $parameters));
    }

    /** @param list<array<string, mixed>> $rows @return list<array{name: string, category: string}> */
    private static function formatTags(array $rows): array
    {
        return array_map(
            static fn(array $row): array => [
                'name' => (string)$row['name'],
                'category' => (string)$row['category'],
            ],
            $rows,
        );
    }
}
