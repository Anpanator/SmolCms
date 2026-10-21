<?php

declare(strict_types=1);

namespace SmolCms\Test\Service\DB;

use DateTime;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PDO;
use SmolCms\Data\Constant\AccessLevel;
use SmolCms\Data\Constant\AuditActionType;
use SmolCms\Data\Persistence\AuditLogEntity;
use SmolCms\Data\Persistence\UserEntity;
use SmolCms\Service\DB\AuditLogService;
use SmolCms\Service\DB\UserService;
use SmolCms\TestUtils\Attributes\Autowire;
use SmolCms\TestUtils\FunctionalTestCase;

class AuditLogServiceTest extends FunctionalTestCase
{
    #[Autowire]
    private AuditLogService $auditLogService;

    #[Autowire]
    private UserService $userService;

    #[Autowire]
    private PDO $pdo;

    public function testRecordAndGetEntriesMapPersistedLogData(): void
    {
        $user = $this->createUser('audit-record-' . bin2hex(random_bytes(4)), 'Audit Record User');
        $context = 'image_id=17;tag=recorded';

        $this->auditLogService->record($user->getId(), AuditActionType::TAG_ADDED, $context);
        $result = $this->auditLogService->getEntries(1, $user->getLoginName());

        self::assertSame(1, $result['total']);
        self::assertSame(1, $result['page']);
        self::assertSame(30, $result['perPage']);
        self::assertCount(1, $result['entries']);
        $entry = $result['entries'][0];
        self::assertGreaterThan(0, $entry['id']);
        self::assertSame(
            [
                'id' => $user->getId(),
                'loginName' => $user->getLoginName(),
                'displayName' => $user->getDisplayName(),
            ],
            $entry['user'],
        );
        self::assertSame(AuditActionType::TAG_ADDED->value, $entry['actionType']);
        self::assertSame($context, $entry['context']);
        self::assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $entry['time']);
    }

    public function testGetRecentEntriesForUserReturnsNewestEntriesFirstAndHonorsLimit(): void
    {
        $user = $this->createUser('audit-recent-' . bin2hex(random_bytes(4)), 'Audit Recent User');
        $actionTypes = [
            AuditActionType::TAG_ADDED,
            AuditActionType::TAG_REMOVED,
            AuditActionType::TAG_DELETED,
            AuditActionType::TAG_ADDED,
            AuditActionType::TAG_REMOVED,
        ];
        foreach ($actionTypes as $index => $actionType) {
            $this->auditLogService->saveAsNew(new AuditLogEntity(
                id: null,
                userId: $user->getId(),
                actionType: $actionType,
                context: 'recent-' . $index,
                created: new DateTime('2026-10-' . (25 - $index) . ' 12:00:00'),
            ));
        }

        $entries = $this->auditLogService->getRecentEntriesForUser($user->getId(), 3);

        self::assertSame(['recent-0', 'recent-1', 'recent-2'], array_column($entries, 'context'));
        self::assertSame([
            AuditActionType::TAG_ADDED->value,
            AuditActionType::TAG_REMOVED->value,
            AuditActionType::TAG_DELETED->value,
        ], array_column($entries, 'actionType'));
        self::assertSame(
            ['2026-10-25 12:00:00', '2026-10-24 12:00:00', '2026-10-23 12:00:00'],
            array_column($entries, 'time'),
        );
        foreach ($entries as $entry) {
            self::assertSame(['id', 'actionType', 'context', 'time'], array_keys($entry));
            self::assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $entry['time']);
        }
    }

    public function testGetRecentEntriesForUserReturnsEmptyWhenUserHasNoEntries(): void
    {
        $user = $this->createUser('audit-recent-empty-' . bin2hex(random_bytes(4)), 'Audit Empty User');

        self::assertSame([], $this->auditLogService->getRecentEntriesForUser($user->getId(), 15));
    }

    public static function invalidRecentEntryLimitProvider(): array
    {
        return [
            'zero' => [0],
            'negative' => [-1],
        ];
    }

    #[DataProvider('invalidRecentEntryLimitProvider')]
    public function testGetRecentEntriesForUserRejectsNonPositiveLimits(int $limit): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->auditLogService->getRecentEntriesForUser(1, $limit);
    }

    public function testSearchEscapesLikeMetacharactersInLoginAndDisplayNames(): void
    {
        $marker = 'audit-search-' . bin2hex(random_bytes(4)) . '-';
        $cases = [
            'percent' => [$marker . '%tail', $marker . 'Xtail'],
            'underscore' => [$marker . '_tail', $marker . 'Xtail'],
            'backslash' => [$marker . '\\tail', $marker . 'tail'],
        ];
        $decoyUsers = [];

        foreach ($cases as $label => [$literalQuery, $wildcardDecoy]) {
            $matchingUser = $this->createUser('audit-' . $literalQuery, 'Matching ' . $label);
            $this->auditLogService->record($matchingUser->getId(), AuditActionType::TAG_ADDED, 'matching');
            if (!isset($decoyUsers[$wildcardDecoy])) {
                $decoyUser = $this->createUser('audit-' . $wildcardDecoy, 'Decoy ' . $label);
                $this->auditLogService->record($decoyUser->getId(), AuditActionType::TAG_REMOVED, 'decoy');
                $decoyUsers[$wildcardDecoy] = true;
            }

            $result = $this->auditLogService->getEntries(1, $literalQuery);

            self::assertSame(1, $result['total'], "The $label search metacharacter must be literal.");
            self::assertSame([$matchingUser->getId()], self::entryUserIds($result['entries']));
        }
    }

    public function testNumericSearchMatchesUserIdsAndNamesWithLeadingZeroAndOverflowInputs(): void
    {
        $numericName = '74821963';
        $loginMatch = $this->createUser('audit-login-' . $numericName, 'Login Numeric Match');
        $displayMatch = $this->createUser('audit-display-numeric', 'Display Numeric ' . $numericName);
        $this->auditLogService->record($loginMatch->getId(), AuditActionType::TAG_ADDED, 'login match');
        $this->auditLogService->record($displayMatch->getId(), AuditActionType::TAG_REMOVED, 'display match');

        $nameResults = $this->auditLogService->getEntries(1, $numericName);
        self::assertSame(2, $nameResults['total']);
        self::assertEqualsCanonicalizing(
            [$loginMatch->getId(), $displayMatch->getId()],
            self::entryUserIds($nameResults['entries']),
        );

        $idResults = $this->auditLogService->getEntries(1, (string)$loginMatch->getId());
        self::assertContains($loginMatch->getId(), self::entryUserIds($idResults['entries']));
        $leadingZeroResults = $this->auditLogService->getEntries(1, '000000' . $loginMatch->getId());
        self::assertSame([$loginMatch->getId()], self::entryUserIds($leadingZeroResults['entries']));

        $zeroName = $this->createUser('audit-zero-000', 'Zero Numeric Search');
        $this->auditLogService->record($zeroName->getId(), AuditActionType::TAG_ADDED, 'zero name');
        $zeroResults = $this->auditLogService->getEntries(1, '000');
        self::assertContains($zeroName->getId(), self::entryUserIds($zeroResults['entries']));

        $overflow = str_repeat('9', strlen((string)PHP_INT_MAX) + 5);
        $overflowUser = $this->createUser('audit-overflow-' . $overflow, 'Overflow Numeric Search');
        $this->auditLogService->record($overflowUser->getId(), AuditActionType::TAG_ADDED, 'overflow name');
        $overflowResults = $this->auditLogService->getEntries(1, $overflow);
        self::assertSame([$overflowUser->getId()], self::entryUserIds($overflowResults['entries']));
    }

    public function testEmptySearchAndPagination(): void
    {
        $suffix = bin2hex(random_bytes(4));
        $user = $this->createUser('audit-page-' . $suffix, 'Audit Page ' . $suffix);
        for ($index = 0; $index < 35; $index++) {
            $this->auditLogService->record(
                $user->getId(),
                $index % 2 === 0 ? AuditActionType::TAG_ADDED : AuditActionType::TAG_REMOVED,
                'page-' . $index,
            );
        }

        $firstPage = $this->auditLogService->getEntries(1, $suffix);
        $secondPage = $this->auditLogService->getEntries(2, $suffix);

        self::assertSame(35, $firstPage['total']);
        self::assertSame(30, $firstPage['perPage']);
        self::assertCount(30, $firstPage['entries']);
        self::assertSame(2, $secondPage['page']);
        self::assertCount(5, $secondPage['entries']);
        self::assertGreaterThan(
            $secondPage['entries'][0]['id'],
            $firstPage['entries'][array_key_last($firstPage['entries'])]['id'],
        );

        $expectedTotal = (int)$this->pdo->query(
            'SELECT COUNT(*) FROM audit_log l INNER JOIN user u ON u.id = l.user_id'
        )->fetchColumn();
        $unfiltered = $this->auditLogService->getEntries(1, '');
        self::assertSame($expectedTotal, $unfiltered['total']);
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

    /** @param list<array{user: array{id: int}}> $entries @return list<int> */
    private static function entryUserIds(array $entries): array
    {
        return array_map(static fn(array $entry): int => $entry['user']['id'], $entries);
    }
}
