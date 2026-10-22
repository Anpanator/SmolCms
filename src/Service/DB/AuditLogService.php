<?php

declare(strict_types=1);

namespace SmolCms\Service\DB;

use DateTime;
use InvalidArgumentException;
use PDO;
use SmolCms\Data\Constant\AuditActionType;
use SmolCms\Data\Persistence\AuditLogEntity;
use SmolCms\Service\Core\CaseConverter;
use SmolCms\Service\Tag\TagService;

readonly class AuditLogService extends EntityService
{
    private const int PER_PAGE = 30;

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

    public function record(int $userId, AuditActionType $actionType, string $context): void
    {
        $this->saveAsNew(new AuditLogEntity(
            id: null,
            userId: $userId,
            actionType: $actionType,
            context: $context,
            created: new DateTime(),
        ));
    }

    /**
     * @return list<array{id: int, actionType: int, context: string, time: string}>
     */
    public function getRecentEntriesForUser(int $userId, int $limit): array
    {
        if ($limit < 1) {
            throw new InvalidArgumentException('Limit must be a positive integer.');
        }

        $criteria = new QueryCriteria();
        $criteria->select(AuditLogEntity::class)
            ->andWhere('user_id = :user_id')
            ->withParameters(['user_id' => $userId])
            ->orderBy('created', QueryCriteria::ORDER_DESC)
            ->orderBy('id', QueryCriteria::ORDER_DESC)
            ->maxResults($limit);

        $entries = [];
        foreach ($this->execute($criteria) ?? [] as $auditLog) {
            $entries[] = [
                'id' => (int)$auditLog->getId(),
                'actionType' => $auditLog->getActionType()->value,
                'context' => $auditLog->getContext(),
                'time' => $auditLog->getCreated()->format('Y-m-d H:i:s'),
            ];
        }

        return $entries;
    }

    /**
     * @return array{entries: list<array{id: int, user: array{id: int, loginName: string, displayName: string}, actionType: int, context: string, time: string}>, page: int, perPage: int, total: int}
     */
    public function getEntries(int $page, string $q): array
    {
        if ($page < 1 || $page > intdiv(PHP_INT_MAX, self::PER_PAGE) + 1) {
            throw new InvalidArgumentException('Page must be a positive integer.');
        }

        $countCriteria = new QueryCriteria();
        $countCriteria->select(AuditLogEntity::class);
        self::applySearchFilter($countCriteria, $q);
        $total = $this->countResults($countCriteria);

        $criteria = new QueryCriteria();
        $criteria->select(AuditLogEntity::class)
            ->orderBy('id', QueryCriteria::ORDER_DESC)
            ->maxResults(self::PER_PAGE)
            ->skipResults(($page - 1) * self::PER_PAGE);
        self::applySearchFilter($criteria, $q);
        $auditLogs = $this->execute($criteria) ?? [];

        $userIds = [];
        foreach ($auditLogs as $auditLog) {
            $userIds[$auditLog->getUserId()] = $auditLog->getUserId();
        }
        $users = $this->userService->findByIds(array_values($userIds));

        $entries = [];
        foreach ($auditLogs as $auditLog) {
            $user = $users[$auditLog->getUserId()] ?? null;
            if ($user === null) {
                continue;
            }
            $entries[] = [
                'id' => (int)$auditLog->getId(),
                'user' => [
                    'id' => (int)$user->getId(),
                    'loginName' => $user->getLoginName(),
                    'displayName' => $user->getDisplayName(),
                ],
                'actionType' => $auditLog->getActionType()->value,
                'context' => $auditLog->getContext(),
                'time' => $auditLog->getCreated()->format('Y-m-d H:i:s'),
            ];
        }

        return [
            'entries' => $entries,
            'page' => $page,
            'perPage' => self::PER_PAGE,
            'total' => $total,
        ];
    }

    private static function applySearchFilter(QueryCriteria $criteria, string $q): void
    {
        if ($q === '') {
            // Mirrors the previous INNER JOIN with user.
            $criteria->andWhere('user_id IN (SELECT id FROM user)');
            return;
        }

        $conditions = [];
        $parameters = [];
        $numericUserId = self::parseNumericId($q);
        if ($numericUserId !== null) {
            $conditions[] = 'id = :user_id';
            $parameters['user_id'] = $numericUserId;
        }
        $pattern = '%' . TagService::escapeLikePattern($q) . '%';
        $conditions[] = "login_name LIKE :login_query ESCAPE '\\'";
        $conditions[] = "display_name LIKE :display_query ESCAPE '\\'";
        $parameters['login_query'] = $pattern;
        $parameters['display_query'] = $pattern;

        $criteria->andWhere(
            'user_id IN (SELECT id FROM user WHERE ' . implode(' OR ', $conditions) . ')'
        )->withParameters($parameters);
    }

    private static function parseNumericId(string $q): ?int
    {
        if (!ctype_digit($q)) {
            return null;
        }

        $digits = ltrim($q, '0');
        if ($digits === '') {
            return 0;
        }
        $maximum = (string)PHP_INT_MAX;
        if (strlen($digits) > strlen($maximum)
            || (strlen($digits) === strlen($maximum) && strcmp($digits, $maximum) > 0)) {
            return null;
        }
        return (int)$digits;
    }
}
