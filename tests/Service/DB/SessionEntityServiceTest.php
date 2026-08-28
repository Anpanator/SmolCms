<?php
declare(strict_types=1);

namespace SmolCms\Test\Service\DB;

use DateTime;
use SmolCms\Data\Persistence\SessionEntity;
use SmolCms\Service\DB\SessionEntityService;
use SmolCms\TestUtils\Attributes\Autowire;
use SmolCms\TestUtils\FunctionalTestCase;

class SessionEntityServiceTest extends FunctionalTestCase
{
    #[Autowire]
    private SessionEntityService $sessionEntityService;

    public function testFindOneBySessionIdAndExists_returnsSavedSession(): void
    {
        $session = new SessionEntity(
            id: null,
            sessionId: 'session-service-found',
            created: new DateTime('2024-02-03 04:05:06'),
            data: 'session data',
        );

        $this->sessionEntityService->saveAsNew($session);

        $result = $this->sessionEntityService->findOneBySessionId('session-service-found');

        self::assertNotNull($result);
        self::assertSame($session->getId(), $result->getId());
        self::assertSame('session data', $result->getData());
        self::assertSame('2024-02-03 04:05:06', $result->getCreated()?->format('Y-m-d H:i:s'));
        self::assertTrue($this->sessionEntityService->sessionIdExists('session-service-found'));
    }

    public function testFindOneBySessionIdAndExists_returnsNotFoundForMissingSession(): void
    {
        self::assertNull($this->sessionEntityService->findOneBySessionId('session-service-missing'));
        self::assertFalse($this->sessionEntityService->sessionIdExists('session-service-missing'));
    }

    public function testDeleteBySessionId_deletesOnlyMatchingSession(): void
    {
        $deletedSession = new SessionEntity(
            id: null,
            sessionId: 'session-service-delete',
            created: new DateTime('2024-01-01 00:00:00'),
            data: 'to delete',
        );
        $retainedSession = new SessionEntity(
            id: null,
            sessionId: 'session-service-retain',
            created: new DateTime('2024-01-01 00:00:00'),
            data: 'to retain',
        );
        $this->sessionEntityService->saveAsNew($deletedSession);
        $this->sessionEntityService->saveAsNew($retainedSession);

        $this->sessionEntityService->deleteBySessionId('session-service-delete');

        self::assertNull($this->sessionEntityService->findOneBySessionId('session-service-delete'));
        self::assertNotNull($this->sessionEntityService->findOneBySessionId('session-service-retain'));
    }

    public function testDeleteOlderThan_deletesExpiredSessionsOnly(): void
    {
        $expiredSession = new SessionEntity(
            id: null,
            sessionId: 'session-service-expired',
            created: new DateTime('2000-01-01 00:00:00'),
            data: 'expired',
        );
        $currentSession = new SessionEntity(
            id: null,
            sessionId: 'session-service-current',
            created: new DateTime(),
            data: 'current',
        );
        $this->sessionEntityService->saveAsNew($expiredSession);
        $this->sessionEntityService->saveAsNew($currentSession);

        $this->sessionEntityService->deleteOlderThan(3600);

        self::assertNull($this->sessionEntityService->findOneBySessionId('session-service-expired'));
        self::assertNotNull($this->sessionEntityService->findOneBySessionId('session-service-current'));
    }
}
