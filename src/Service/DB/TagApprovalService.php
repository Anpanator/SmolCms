<?php

declare(strict_types=1);

namespace SmolCms\Service\DB;

use SmolCms\Data\Persistence\TagApprovalEntity;

readonly class TagApprovalService extends EntityService
{
    public function countForUser(int $userId): int
    {
        $criteria = new QueryCriteria();
        $criteria->select(TagApprovalEntity::class)
            ->andWhere('user_id = :user_id')
            ->withParameters(['user_id' => $userId]);
        return $this->countResults($criteria);
    }
}
