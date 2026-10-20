<?php
declare(strict_types=1);

namespace SmolCms\Service\DB;

use InvalidArgumentException;
use SmolCms\Data\Constant\AccessLevel;
use SmolCms\Data\Persistence\UserEntity;
use SmolCms\Exception\UserUpdateForbiddenException;
use SmolCms\Service\Tag\TagService;

readonly class UserService extends EntityService
{
    private const int SEARCH_PER_PAGE = 30;

    public function findOneById(int $id): ?UserEntity
    {
        $qc = new QueryCriteria();
        $qc->select(UserEntity::class)
            ->andWhere('id = :id')
            ->withParameters(['id' => $id])
            ->maxResults(1);
        $result = $this->execute($qc);
        return $result[0] ?? null;
    }

    /**
     * @return array{users: list<array{id: int, loginName: string, displayName: string, accessLevel: AccessLevel, registerDate: string}>, page: int, perPage: int, total: int}
     */
    public function searchUsers(int $page, string $q): array
    {
        if ($page < 1 || $page > intdiv(PHP_INT_MAX, self::SEARCH_PER_PAGE) + 1) {
            throw new InvalidArgumentException('Page must be a positive integer.');
        }

        $countCriteria = new QueryCriteria();
        $countCriteria->select(UserEntity::class);
        self::applySearchFilter($countCriteria, $q);
        $total = $this->countResults($countCriteria);

        $criteria = new QueryCriteria();
        $criteria->select(UserEntity::class)
            ->orderBy('id')
            ->maxResults(self::SEARCH_PER_PAGE)
            ->skipResults(($page - 1) * self::SEARCH_PER_PAGE);
        self::applySearchFilter($criteria, $q);
        $users = $this->execute($criteria) ?? [];

        return [
            'users' => array_map(
                static fn(UserEntity $user): array => [
                    'id' => $user->getId() ?? 0,
                    'loginName' => $user->getLoginName(),
                    'displayName' => $user->getDisplayName(),
                    'accessLevel' => $user->getAccessLevel(),
                    'registerDate' => $user->getRegisterDate()->format('Y-m-d H:i:s'),
                ],
                $users,
            ),
            'page' => $page,
            'perPage' => self::SEARCH_PER_PAGE,
            'total' => $total,
        ];
    }

    public function updateUser(
        int $id,
        string $displayName,
        AccessLevel $accessLevel,
        int $actorId,
        ?string $loginName = null,
    ): ?UserEntity
    {
        return $this->withinTransaction(function () use ($id, $displayName, $accessLevel, $actorId, $loginName): ?UserEntity {
            $actor = $this->findOneById($actorId);
            if ($actor === null) {
                throw new UserUpdateForbiddenException('The user making this update no longer exists.');
            }

            $user = $this->findOneById($id);
            if ($user === null) {
                return null;
            }

            if ($id === $actorId
                || $user->getAccessLevel()->value >= $actor->getAccessLevel()->value
                || $accessLevel->value >= $actor->getAccessLevel()->value) {
                throw new UserUpdateForbiddenException('The user is not allowed to update this account or access level.');
            }

            if ($loginName !== null) {
                $user->setLoginName($loginName);
            }
            $user->setDisplayName($displayName);
            $user->setAccessLevel($accessLevel);
            $this->update($user);
            return $user;
        });
    }

    public function findOneByLoginName(string $loginName): ?UserEntity
    {
        $qc = new QueryCriteria();
        $qc->select(UserEntity::class)
            ->andWhere('login_name = :loginName')
            ->withParameters(['loginName' => $loginName])
            ->maxResults(1);
        $result = $this->execute($qc);
        return $result[0] ?? null;
    }

    private static function applySearchFilter(QueryCriteria $criteria, string $q): void
    {
        if ($q === '') {
            return;
        }

        $pattern = '%' . TagService::escapeLikePattern($q) . '%';
        $parameters = [
            'login_name_query' => $pattern,
            'display_name_query' => $pattern,
        ];
        $numericId = self::parseNumericId($q);
        if ($numericId !== null) {
            $criteria->andWhere('id = :user_id')
                ->orWhere("login_name LIKE :login_name_query ESCAPE '\\'")
                ->orWhere("display_name LIKE :display_name_query ESCAPE '\\'");
            $parameters['user_id'] = $numericId;
        } else {
            $criteria->andWhere("login_name LIKE :login_name_query ESCAPE '\\'")
                ->orWhere("display_name LIKE :display_name_query ESCAPE '\\'");
        }
        $criteria->withParameters($parameters);
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
