<?php

declare(strict_types=1);

namespace SmolCms\Service\Tag;

use DateTime;
use InvalidArgumentException;
use PDO;
use SmolCms\Data\Persistence\UserTagUsageEntity;
use SmolCms\Service\Core\CaseConverter;
use SmolCms\Service\DB\EntityAttributeProcessor;
use SmolCms\Service\DB\EntityService;
use SmolCms\Service\DB\QueryBuilder;
use SmolCms\Service\DB\QueryCriteria;

/** Tracks user-driven tag activity and builds each user's quick-tag lists. */
readonly class UserTagUsageService extends EntityService
{
    private const string EXCLUDED_TAG = 'tagme';
    private const int GLOBAL_LIMIT = 20;
    private const int USER_LIMIT = 10;

    public function __construct(
        PDO $pdo,
        CaseConverter $caseConverter,
        QueryBuilder $queryBuilder,
        EntityAttributeProcessor $entityAttributeProcessor,
        private TagService $tagService,
    )
    {
        parent::__construct($pdo, $caseConverter, $queryBuilder, $entityAttributeProcessor);
    }

    /** Adds one successful user-driven image/tag association to that user's usage. */
    public function recordTagAdded(int $userId, string $tagName): void
    {
        $tagId = $this->findTrackableTagId($tagName);
        if ($userId < 1 || $tagId === null) {
            return;
        }

        $this->withinTransaction(function () use ($userId, $tagId): void {
            $usage = $this->findUsage($userId, $tagId);
            if ($usage === null) {
                $this->saveAsNew(new UserTagUsageEntity(
                    userId: $userId,
                    tagId: $tagId,
                    usageCount: 1,
                    lastUsedAt: new DateTime(),
                ));
                return;
            }

            $usage->setUsageCount($usage->getUsageCount() + 1);
            $usage->setLastUsedAt(new DateTime());
            $this->saveUsage($usage);
        });
    }

    /** Records a successful removal, preserving the last time the tag was added. */
    public function recordTagRemoved(int $userId, string $tagName): void
    {
        $tagId = $this->findTrackableTagId($tagName);
        if ($userId < 1 || $tagId === null) {
            return;
        }

        $this->withinTransaction(function () use ($userId, $tagId): void {
            $usage = $this->findUsage($userId, $tagId);
            if ($usage === null) {
                $this->saveAsNew(new UserTagUsageEntity(
                    userId: $userId,
                    tagId: $tagId,
                    usageCount: 0,
                    lastUsedAt: null,
                ));
                return;
            }

            $usage->setUsageCount(max(0, $usage->getUsageCount() - 1));
            $this->saveUsage($usage);
        });
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
        $global = $this->tagService->getMostUsedTags(self::GLOBAL_LIMIT, self::EXCLUDED_TAG);

        $usageRows = $this->findUsageForUser($userId);
        $tagSummaries = $this->tagService->getTagSummariesByIds(array_values(array_unique(
            array_map(static fn(UserTagUsageEntity $usage): int => $usage->getTagId(), $usageRows),
        )));

        $mostUsedRows = $this->findMostUsed($usageRows, $tagSummaries);
        $userMostUsed = array_map(
            static fn(array $row): array => ['name' => $row['name'], 'category' => $row['category']],
            $mostUsedRows,
        );
        $userRecent = $this->findRecent($usageRows, $tagSummaries);

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

        return $this->tagService->findTagIdByName($normalizedName);
    }

    private function findUsage(int $userId, int $tagId): ?UserTagUsageEntity
    {
        $criteria = new QueryCriteria();
        $criteria->select(UserTagUsageEntity::class)
            ->andWhere('user_id = :user_id')
            ->andWhere('tag_id = :tag_id')
            ->withParameters(['user_id' => $userId, 'tag_id' => $tagId])
            ->maxResults(1);
        return ($this->execute($criteria) ?? [])[0] ?? null;
    }

    /** @return list<UserTagUsageEntity> */
    private function findUsageForUser(int $userId): array
    {
        $criteria = new QueryCriteria();
        $criteria->select(UserTagUsageEntity::class)
            ->andWhere('user_id = :user_id')
            ->withParameters(['user_id' => $userId]);
        return $this->execute($criteria) ?? [];
    }

    private function saveUsage(UserTagUsageEntity $usage): void
    {
        $criteria = new QueryCriteria();
        $criteria->update(UserTagUsageEntity::class)
            ->andWhere('user_id = :user_id')
            ->andWhere('tag_id = :tag_id')
            ->withParameters([
                'user_id' => $usage->getUserId(),
                'tag_id' => $usage->getTagId(),
                'usage_count' => $usage->getUsageCount(),
                'last_used_at' => $usage->getLastUsedAt()?->format('Y-m-d H:i:s'),
            ]);
        $this->execute($criteria);
    }

    /**
     * @param list<UserTagUsageEntity> $usageRows
     * @param array<int, array{name: string, category: string}> $tagSummaries
     * @return list<array{tagId: int, name: string, category: string, usageCount: int}>
     */
    private function findMostUsed(array $usageRows, array $tagSummaries): array
    {
        $rows = [];
        foreach ($usageRows as $usage) {
            $summary = $tagSummaries[$usage->getTagId()] ?? null;
            if ($usage->getUsageCount() < 1
                || $summary === null
                || TagService::normalizeTagName($summary['name']) === self::EXCLUDED_TAG) {
                continue;
            }
            $rows[] = [
                'tagId' => $usage->getTagId(),
                'name' => $summary['name'],
                'category' => $summary['category'],
                'usageCount' => $usage->getUsageCount(),
            ];
        }
        usort($rows, static function (array $a, array $b): int {
            $countDifference = $b['usageCount'] <=> $a['usageCount'];
            if ($countDifference !== 0) {
                return $countDifference;
            }
            return strcasecmp($a['name'], $b['name']) ?: strcmp($a['name'], $b['name']);
        });

        return array_slice($rows, 0, self::USER_LIMIT);
    }

    /**
     * Lists recently applied tags that are still attached, ordered by last use.
     * The list may overlap with the most-used list.
     *
     * @param list<UserTagUsageEntity> $usageRows
     * @param array<int, array{name: string, category: string}> $tagSummaries
     * @return list<array{name: string, category: string}>
     */
    private function findRecent(array $usageRows, array $tagSummaries): array
    {
        $rows = [];
        foreach ($usageRows as $usage) {
            $summary = $tagSummaries[$usage->getTagId()] ?? null;
            if ($usage->getUsageCount() < 1
                || $usage->getLastUsedAt() === null
                || $summary === null
                || TagService::normalizeTagName($summary['name']) === self::EXCLUDED_TAG) {
                continue;
            }
            $rows[] = [
                'name' => $summary['name'],
                'category' => $summary['category'],
                'lastUsedAt' => $usage->getLastUsedAt(),
            ];
        }
        usort($rows, static function (array $a, array $b): int {
            $timeDifference = $b['lastUsedAt'] <=> $a['lastUsedAt'];
            if ($timeDifference !== 0) {
                return $timeDifference;
            }
            return strcasecmp($a['name'], $b['name']) ?: strcmp($a['name'], $b['name']);
        });

        return array_map(
            static fn(array $row): array => ['name' => $row['name'], 'category' => $row['category']],
            array_slice($rows, 0, self::USER_LIMIT),
        );
    }
}
