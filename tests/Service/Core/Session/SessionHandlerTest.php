<?php

declare(strict_types=1);

namespace SmolCms\Test\Service\Core\Session;

use DateTime;
use RuntimeException;
use SmolCms\Data\Persistence\SessionEntity;
use SmolCms\Service\Core\Session\SessionHandler;
use SmolCms\Service\DB\SessionEntityService;
use SmolCms\TestUtils\Attributes\Autowire;
use SmolCms\TestUtils\FunctionalTestCase;

class SessionHandlerTest extends FunctionalTestCase
{
    #[Autowire]
    private SessionHandler $sessionHandler;

    #[Autowire]
    private SessionEntityService $sessionEntityService;

    /** @var string[] */
    private array $sessionIds = [];

    protected function tearDown(): void
    {
        foreach (array_unique($this->sessionIds) as $sessionId) {
            $this->sessionHandler->destroy($sessionId);
        }
        parent::tearDown();
    }

    public function testWriteAndRead_RoundTrip(): void
    {
        $sessionId = $this->trackSession('handler-round-trip');
        $data = 'user|s:4:"test";';

        self::assertTrue($this->sessionHandler->write($sessionId, $data));
        self::assertSame($data, $this->sessionHandler->read($sessionId));
    }

    public function testWrite_UpdatesExistingSession(): void
    {
        $sessionId = $this->trackSession('handler-update');

        $this->sessionHandler->write($sessionId, 'first');
        $existingSession = $this->sessionEntityService->findOneBySessionId($sessionId);
        self::assertNotNull($existingSession);
        $existingId = $existingSession->getId();

        $this->sessionHandler->write($sessionId, 'second');
        $updatedSession = $this->sessionEntityService->findOneBySessionId($sessionId);

        self::assertNotNull($updatedSession);
        self::assertSame($existingId, $updatedSession->getId());
        self::assertSame('second', $updatedSession->getData());
    }

    public function testDestroy_DeletesSession(): void
    {
        $sessionId = $this->trackSession('handler-destroy');
        $this->sessionHandler->write($sessionId, 'data');

        self::assertTrue($this->sessionHandler->destroy($sessionId));
        self::assertSame('', $this->sessionHandler->read($sessionId));
    }

    public function testValidateId_ReportsWhetherSessionExists(): void
    {
        $sessionId = $this->trackSession('handler-validate');
        $this->sessionHandler->write($sessionId, 'data');

        self::assertTrue($this->sessionHandler->validateId($sessionId));
        self::assertFalse($this->sessionHandler->validateId('handler-missing'));
    }

    public function testGc_DeletesExpiredSessions(): void
    {
        $expiredSessionId = $this->trackSession('handler-expired');
        $activeSessionId = $this->trackSession('handler-active');

        $this->sessionEntityService->saveAsNew(new SessionEntity(
            id: null,
            sessionId: $expiredSessionId,
            created: new DateTime('2000-01-01 00:00:00'),
            data: 'expired-data',
        ));
        $this->sessionEntityService->saveAsNew(new SessionEntity(
            id: null,
            sessionId: $activeSessionId,
            created: new DateTime(),
            data: 'active-data',
        ));

        self::assertTrue($this->sessionHandler->gc(3600));
        self::assertSame('', $this->sessionHandler->read($expiredSessionId));
        self::assertSame('active-data', $this->sessionHandler->read($activeSessionId));
    }

    public function testWrite_ThrowsForInvalidSessionData(): void
    {
        $sessionId = $this->trackSession('handler-invalid-data');

        try {
            $this->sessionHandler->write($sessionId, str_repeat('x', 2 ** 24));
            self::fail('Writing oversized session data should fail validation.');
        } catch (RuntimeException $exception) {
            self::assertStringContainsString('Invalid Session', $exception->getMessage());
        }

        self::assertNull($this->sessionEntityService->findOneBySessionId($sessionId));
    }

    private function trackSession(string $sessionId): string
    {
        $this->sessionIds[] = $sessionId;
        return $sessionId;
    }
}
