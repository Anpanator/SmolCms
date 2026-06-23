<?php
declare(strict_types=1);

namespace SmolCms\Service\DB;

use SmolCms\Data\Persistence\UserEntity;

readonly class UserService extends EntityService
{

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
}