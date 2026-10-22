<?php
declare(strict_types=1);

namespace SmolCms\Service\DB;

use DateTime;
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

    /**
     * Marks the session as active so garbage collection does not expire it.
     * The creation time doubles as the last-activity timestamp.
     */
    public function touchBySessionId(string $sessionId): bool
    {
        $session = $this->findOneBySessionId($sessionId);
        if ($session === null) {
            return false;
        }

        $session->setCreated(new DateTime());
        $this->saveOrUpdate($session);
        return true;
    }

    public function deleteOlderThan(int $seconds): int
    {
        $qc = new QueryCriteria();
        $qc->delete(SessionEntity::class)
            ->andWhere("created < :created")
            ->withParameters(["created" => date("Y-m-d H:i:s", time() - $seconds)]);
        $statement = $this->prepare($qc);
        $statement->execute();
        return $statement->rowCount();
    }

    public function deleteBySessionId(string $sessionId): void
    {
        $qc = new QueryCriteria();
        $qc->delete(SessionEntity::class)
            ->andWhere("session_id = :sessionId")
            ->withParameters(["sessionId" => $sessionId]);
        $this->execute($qc);
    }
}
