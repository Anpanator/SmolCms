<?php

declare(strict_types=1);

namespace SmolCms\Service\DB;

use DateTime;
use PDO;
use SmolCms\Data\Constant\AuditActionType;
use SmolCms\Data\Persistence\AuditLogEntity;
use SmolCms\Service\Tag\TagService;
use InvalidArgumentException;

readonly class AuditLogService extends EntityService
{
    private const int PER_PAGE = 30;

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

        $statement = $this->pdo->prepare(
            'SELECT id, action_type, context, created FROM audit_log '
            . 'WHERE user_id = :user_id ORDER BY created DESC, id DESC LIMIT :limit'
        );
        $statement->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
        $statement->execute();

        $entries = [];
        while (($row = $statement->fetch(PDO::FETCH_ASSOC)) !== false) {
            $entries[] = [
                'id' => (int)$row['id'],
                'actionType' => (int)$row['action_type'],
                'context' => (string)$row['context'],
                'time' => (new DateTime((string)$row['created']))->format('Y-m-d H:i:s'),
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

        $filterSql = '';
        $parameters = [];
        if ($q !== '') {
            $conditions = [];
            $numericUserId = self::parseNumericId($q);
            if ($numericUserId !== null) {
                $conditions[] = 'l.user_id = :user_id';
                $parameters['user_id'] = $numericUserId;
            }
            $pattern = '%' . TagService::escapeLikePattern($q) . '%';
            $conditions[] = "u.login_name LIKE :login_query ESCAPE '\\'";
            $conditions[] = "u.display_name LIKE :display_query ESCAPE '\\'";
            $parameters['login_query'] = $pattern;
            $parameters['display_query'] = $pattern;
            $filterSql = ' WHERE (' . implode(' OR ', $conditions) . ')';
        }

        $countStatement = $this->pdo->prepare(
            'SELECT COUNT(*) FROM audit_log l INNER JOIN user u ON u.id = l.user_id' . $filterSql
        );
        $countStatement->execute($parameters);
        $total = (int)$countStatement->fetchColumn();

        $statement = $this->pdo->prepare(
            'SELECT l.id, l.user_id, u.login_name, u.display_name, l.action_type, l.context, l.created '
            . 'FROM audit_log l INNER JOIN user u ON u.id = l.user_id'
            . $filterSql
            . ' ORDER BY l.id DESC LIMIT :limit OFFSET :offset'
        );
        foreach ($parameters as $name => $value) {
            $statement->bindValue(':' . $name, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $statement->bindValue(':limit', self::PER_PAGE, PDO::PARAM_INT);
        $statement->bindValue(':offset', ($page - 1) * self::PER_PAGE, PDO::PARAM_INT);
        $statement->execute();

        $entries = [];
        while (($row = $statement->fetch(PDO::FETCH_ASSOC)) !== false) {
            $entries[] = [
                'id' => (int)$row['id'],
                'user' => [
                    'id' => (int)$row['user_id'],
                    'loginName' => (string)$row['login_name'],
                    'displayName' => (string)$row['display_name'],
                ],
                'actionType' => (int)$row['action_type'],
                'context' => (string)$row['context'],
                'time' => (new DateTime((string)$row['created']))->format('Y-m-d H:i:s'),
            ];
        }

        return [
            'entries' => $entries,
            'page' => $page,
            'perPage' => self::PER_PAGE,
            'total' => $total,
        ];
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
