<?php

declare(strict_types=1);

namespace SmolCms\Test\Service\Tag;

use PDO;
use SmolCms\Service\Tag\TagService;
use SmolCms\Service\Tag\UserTagUsageService;
use SmolCms\TestUtils\Attributes\Autowire;
use SmolCms\TestUtils\FunctionalTestCase;

class UserTagUsageServiceTest extends FunctionalTestCase
{
    #[Autowire]
    private UserTagUsageService $userTagUsageService;

    #[Autowire]
    private TagService $tagService;

    #[Autowire]
    private PDO $pdo;

    public function testAddIncrementsAndRemoveFloorsUsageWithoutChangingLastUsedAt(): void
    {
        $tagName = 'user-usage-' . bin2hex(random_bytes(4));
        $this->tagService->createTag($tagName);

        $this->userTagUsageService->recordTagAdded(901, $tagName);
        $this->userTagUsageService->recordTagAdded(901, $tagName);
        $afterAdds = $this->findUsage(901, $tagName);
        self::assertNotNull($afterAdds);
        self::assertSame(2, (int)$afterAdds['usage_count']);
        self::assertNotNull($afterAdds['last_used_at']);

        $this->userTagUsageService->recordTagRemoved(901, $tagName);
        $afterFirstRemoval = $this->findUsage(901, $tagName);
        self::assertNotNull($afterFirstRemoval);
        self::assertSame(1, (int)$afterFirstRemoval['usage_count']);
        self::assertSame($afterAdds['last_used_at'], $afterFirstRemoval['last_used_at']);

        $this->userTagUsageService->recordTagRemoved(901, $tagName);
        $this->userTagUsageService->recordTagRemoved(901, $tagName);
        $afterFloor = $this->findUsage(901, $tagName);
        self::assertNotNull($afterFloor);
        self::assertSame(0, (int)$afterFloor['usage_count']);
        self::assertSame($afterAdds['last_used_at'], $afterFloor['last_used_at']);
    }

    public function testRecentListExcludesRemovedTagsAndIncludesAttachedTags(): void
    {
        $userId = 903;
        $prefix = 'user-usage-recent-' . bin2hex(random_bytes(4)) . '-';
        $attachedTagNames = [];
        for ($index = 0; $index < 11; $index++) {
            $tagName = $prefix . 'attached-' . sprintf('%02d', $index);
            $attachedTagNames[] = $tagName;
            self::assertNotNull($this->tagService->createTag($tagName));
            $this->userTagUsageService->recordTagAdded($userId, $tagName);
        }

        $removedTagName = $prefix . 'removed';
        self::assertNotNull($this->tagService->createTag($removedTagName));
        $this->userTagUsageService->recordTagAdded($userId, $removedTagName);
        $this->userTagUsageService->recordTagRemoved($userId, $removedTagName);

        $recentTagNames = array_column(
            $this->userTagUsageService->getQuickTags($userId)['userRecent'],
            'name',
        );

        self::assertContains($attachedTagNames[10], $recentTagNames);
        self::assertNotContains($removedTagName, $recentTagNames);
    }

    public function testRemovingWithoutPriorUsageCreatesZeroRowAndTagmeIsNeverTracked(): void
    {
        $tagName = 'user-usage-removed-' . bin2hex(random_bytes(4));
        $this->tagService->createTag($tagName);

        $this->userTagUsageService->recordTagRemoved(902, $tagName);
        $usage = $this->findUsage(902, $tagName);
        self::assertNotNull($usage);
        self::assertSame(0, (int)$usage['usage_count']);
        self::assertNull($usage['last_used_at']);

        $this->userTagUsageService->recordTagAdded(902, 'TAGME');
        $this->userTagUsageService->recordTagRemoved(902, 'tagme');
        self::assertNull($this->findUsage(902, 'tagme'));
    }

    public function testQuickTagsExcludeTagmeEvenWhenAUsageRowExists(): void
    {
        $userId = 904;
        $tagName = 'user-usage-tagme-exclusion-' . bin2hex(random_bytes(4));
        $this->tagService->createTag($tagName);
        $tagmeId = $this->tagService->findOrCreateTagId('tagme');
        $tagId = $this->tagService->findOrCreateTagId($tagName);

        $statement = $this->pdo->prepare(
            'INSERT INTO user_tag_usage (user_id, tag_id, usage_count, last_used_at) '
            . 'VALUES (:user_id, :tag_id, :usage_count, CURRENT_TIMESTAMP)'
        );
        $statement->execute(['user_id' => $userId, 'tag_id' => $tagmeId, 'usage_count' => 100]);
        $statement->execute(['user_id' => $userId, 'tag_id' => $tagId, 'usage_count' => 1]);

        $quickTags = $this->userTagUsageService->getQuickTags($userId);

        self::assertNotContains('tagme', array_column($quickTags['userMostUsed'], 'name'));
        self::assertNotContains('tagme', array_column($quickTags['userRecent'], 'name'));
        self::assertContains($tagName, array_column($quickTags['userMostUsed'], 'name'));
    }

    /** @return array{usage_count: int|string, last_used_at: string|null}|null */
    private function findUsage(int $userId, string $tagName): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT user_usage.usage_count, user_usage.last_used_at '
            . 'FROM user_tag_usage AS user_usage '
            . 'INNER JOIN tag ON tag.id = user_usage.tag_id '
            . 'WHERE user_usage.user_id = :user_id AND tag.name = :tag_name COLLATE NOCASE'
        );
        $statement->execute(['user_id' => $userId, 'tag_name' => $tagName]);
        $usage = $statement->fetch(PDO::FETCH_ASSOC);
        return is_array($usage) ? $usage : null;
    }
}
