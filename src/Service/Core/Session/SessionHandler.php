<?php
declare(strict_types=1);

namespace SmolCms\Service\Core\Session;

use RuntimeException;
use SessionHandlerInterface;
use SessionUpdateTimestampHandlerInterface;
use SmolCms\Data\Persistence\SessionEntity;
use SmolCms\Service\DB\SessionEntityService;
use SmolCms\Service\Validation\Validator;

readonly class SessionHandler implements SessionHandlerInterface, SessionUpdateTimestampHandlerInterface
{
    public function __construct(
        private SessionEntityService $sessionEntityService,
        private Validator            $validator
    )
    {
    }


    public function close(): bool
    {
        // TODO: release lock once acquired in open()
        return true;
    }

    public function destroy(string $id): bool
    {
        $this->sessionEntityService->deleteBySessionId($id);
        return true;
    }

    public function gc(int $max_lifetime): bool
    {
        $this->sessionEntityService->deleteOlderThan($max_lifetime);
        return true;
    }

    public function open(string $path, string $name): bool
    {
        // TODO: Lock session?
        return true;
    }

    public function read(string $id): string
    {
        $session = $this->sessionEntityService->findOneBySessionId($id);
        return $session ? $session->getData() : '';
    }

    public function write(string $id, string $data): bool
    {
        $session = new SessionEntity(
            id: null,
            sessionId: $id,
            created: null,
            data: $data
        );
        $validationResult = $this->validator->validate($session);
        if (!$validationResult->isValid()) {
            throw new RuntimeException("Invalid Session: {$validationResult->getMessagesAsString()}");
        }
        $this->sessionEntityService->saveAsNew($session);
        return true;
    }

    public function validateId(string $id): bool
    {
        return $this->sessionEntityService->sessionIdExists($id);
    }

    public function updateTimestamp(string $id, string $data): bool
    {
        // TODO: Figure out if this is actually needed
        return true;
    }
}