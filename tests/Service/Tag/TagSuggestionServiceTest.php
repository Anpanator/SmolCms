<?php

declare(strict_types=1);

namespace SmolCms\Test\Service\Tag;

use DateTime;
use PDO;
use SmolCms\Data\Constant\AccessLevel;
use SmolCms\Data\Constant\ImageStatus;
use SmolCms\Data\Persistence\UserEntity;
use SmolCms\Exception\PersistenceException;
use SmolCms\Service\DB\ImageEntityService;
use SmolCms\Service\DB\UserService;
use SmolCms\Service\Tag\TagService;
use SmolCms\Service\Tag\TagSuggestionService;
use SmolCms\TestUtils\Attributes\Autowire;
use SmolCms\TestUtils\FunctionalTestCase;
use SmolCms\TestUtils\ImageTestUtils;

class TagSuggestionServiceTest extends FunctionalTestCase
{
    #[Autowire]
    private TagSuggestionService $tagSuggestionService;

    #[Autowire]
    private TagService $tagService;

    #[Autowire]
    private ImageEntityService $imageEntityService;

    #[Autowire]
    private UserService $userService;

    #[Autowire]
    private PDO $pdo;

    public function testCreateSuggestionsNormalizesDuplicatesAndMapsQueueEntries(): void
    {
        $user = $this->createUser('suggestion-queue-' . bin2hex(random_bytes(4)), 'Suggestion Author');
        $image = ImageTestUtils::createImageEntity(
            $this->imageEntityService,
            userId: $user->getId(),
            status: ImageStatus::PENDING_REVIEW,
        );
        $tagName = 'queue-' . bin2hex(random_bytes(4));

        self::assertSame(
            [$tagName],
            $this->tagSuggestionService->createSuggestions($image->getId(), $user->getId(), [
                '  ' . $tagName . '  ',
                mb_strtoupper($tagName, 'UTF-8'),
            ]),
        );

        $queue = $this->tagSuggestionService->getQueue();
        $matchingQueueEntries = array_values(array_filter(
            $queue,
            static fn(array $entry): bool => $entry['imageId'] === $image->getId(),
        ));
        self::assertCount(1, $matchingQueueEntries);
        self::assertSame($tagName, $matchingQueueEntries[0]['tagName']);
        self::assertSame($image->getId(), $matchingQueueEntries[0]['imageId']);
        self::assertSame(
            ['id' => $user->getId(), 'displayName' => 'Suggestion Author'],
            $matchingQueueEntries[0]['suggestedBy'],
        );
        self::assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $matchingQueueEntries[0]['created']);
    }

    public function testApproveRollsBackTagAttachmentAndSuggestionWhenApprovalInsertFails(): void
    {
        $user = $this->createUser('suggestion-rollback-' . bin2hex(random_bytes(4)), 'Rollback Author');
        $image = ImageTestUtils::createImageEntity(
            $this->imageEntityService,
            userId: $user->getId(),
            status: ImageStatus::PENDING_REVIEW,
        );
        $tagName = 'rollback-' . bin2hex(random_bytes(4));
        $this->tagSuggestionService->createSuggestions($image->getId(), $user->getId(), [$tagName]);
        $suggestionId = $this->findSuggestionId($tagName);
        $triggerName = 'fail_tag_approval_' . bin2hex(random_bytes(4));
        $this->pdo->exec(
            "CREATE TRIGGER $triggerName BEFORE INSERT ON tag_approval "
            . "BEGIN SELECT RAISE(ABORT, 'forced approval insert failure'); END",
        );

        try {
            try {
                $this->tagSuggestionService->approve($suggestionId);
                self::fail('The approval insert failure should abort the approval transaction.');
            } catch (PersistenceException $exception) {
                self::assertStringContainsString('Failed to save new entity', $exception->getMessage());
            }

            self::assertNull($this->tagService->getUsageCount($tagName));
            self::assertSame([], $this->tagService->getTagsForImage($image->getId()));
            self::assertContains($tagName, array_column($this->tagSuggestionService->getQueue(), 'tagName'));
            self::assertSame(0, (int)$this->pdo->query('SELECT COUNT(*) FROM tag_approval')->fetchColumn());
        } finally {
            $this->pdo->exec("DROP TRIGGER IF EXISTS $triggerName");
        }
    }

    public function testCreateSuggestionsSkipsDuplicateConstraintRaceAndContinues(): void
    {
        $user = $this->createUser('suggestion-race-' . bin2hex(random_bytes(4)), 'Race Author');
        $image = ImageTestUtils::createImageEntity(
            $this->imageEntityService,
            userId: $user->getId(),
            status: ImageStatus::PENDING_REVIEW,
        );
        $racedName = 'race-' . bin2hex(random_bytes(4));
        $storedName = 'stored-' . bin2hex(random_bytes(4));
        $triggerName = 'race_tag_suggestion_' . bin2hex(random_bytes(4));
        $this->pdo->exec(
            "CREATE TRIGGER $triggerName BEFORE INSERT ON tag_suggestion "
            . "WHEN NEW.tag_name = '$racedName' "
            . "BEGIN SELECT RAISE(ABORT, 'UNIQUE constraint failed: tag_suggestion.image_id, tag_suggestion.tag_name'); END",
        );

        try {
            self::assertSame(
                [$storedName],
                $this->tagSuggestionService->createSuggestions(
                    $image->getId(),
                    $user->getId(),
                    [$racedName, $storedName],
                ),
            );
            $queuedNames = array_column($this->tagSuggestionService->getQueue(), 'tagName');
            self::assertContains($storedName, $queuedNames);
            self::assertNotContains($racedName, $queuedNames);
            self::assertNull($this->tagService->getUsageCount($racedName));
        } finally {
            $this->pdo->exec("DROP TRIGGER IF EXISTS $triggerName");
        }
    }

    public function testRejectRemovesOnlyTheSuggestionAndIsFalseForUnknownIds(): void
    {
        $user = $this->createUser('suggestion-reject-' . bin2hex(random_bytes(4)), 'Reject Author');
        $image = ImageTestUtils::createImageEntity(
            $this->imageEntityService,
            userId: $user->getId(),
            status: ImageStatus::PENDING_REVIEW,
        );
        $tagName = 'reject-' . bin2hex(random_bytes(4));
        $this->tagSuggestionService->createSuggestions($image->getId(), $user->getId(), [$tagName]);
        $suggestionId = $this->findSuggestionId($tagName);

        self::assertTrue($this->tagSuggestionService->reject($suggestionId));
        self::assertFalse($this->tagSuggestionService->reject($suggestionId));
        self::assertNotContains($tagName, array_column($this->tagSuggestionService->getQueue(), 'tagName'));
        self::assertNull($this->tagService->getUsageCount($tagName));
        self::assertSame([], $this->tagService->getTagsForImage($image->getId()));
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

    private function findSuggestionId(string $tagName): int
    {
        foreach ($this->tagSuggestionService->getQueue() as $suggestion) {
            if ($suggestion['tagName'] === $tagName) {
                return $suggestion['id'];
            }
        }
        self::fail("Missing suggestion for tag '$tagName'.");
    }
}
