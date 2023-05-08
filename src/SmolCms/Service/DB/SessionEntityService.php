<?php
declare(strict_types=1);

namespace SmolCms\Service\DB;

use SmolCms\Data\Persistence\SessionEntity;

readonly class SessionEntityService extends EntityService
{
    public function findOneBySessionId(string $sessionId): ?SessionEntity
    {
        $qc = new QueryCriteria();
        $qc->select(SessionEntity::class)
            ->andWhere('session_id = :sessionId')
            ->maxResults(1)
            ->withParameters(['sessionId' => $sessionId]);
        $result = $this->execute($qc);
        return $result[0] ?? null;
    }

    public function sessionIdExists(string $sessionId): bool
    {
        // TODO: Don't actually load data here.
        $qc = new QueryCriteria();
        $qc->select(SessionEntity::class)
            ->andWhere("session_id = :sessionId")
            ->withParameters(["sessionId" => $sessionId]);
        $result = $this->execute($qc);
        return !empty($result);
    }

    public function deleteOlderThan(int $seconds): void
    {
        $qc = new QueryCriteria();
        $qc->delete(SessionEntity::class)
            ->andWhere("created < :created")
            ->withParameters(["created" => date("Y-m-d H:i:s", time() - $seconds)]);
        $this->execute($qc);
    }
}