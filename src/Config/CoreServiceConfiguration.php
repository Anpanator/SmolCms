<?php

declare(strict_types=1);

namespace SmolCms\Config;


use PDO;
use SmolCms\Data\Business\Service;
use SmolCms\Exception\ServiceConflictException;
use SmolCms\Service\Core\ApplicationStartupHandler;
use SmolCms\Service\Core\Startup\RegisterSessionHandlerStartupAction;
use SmolCms\Service\Core\Startup\ResumeSessionStartupAction;

class CoreServiceConfiguration
{
    /** @var array<string, Service> */
    private array $services;

    public function __construct()
    {
        $services = [
            new Service(
                identifier: PDO::class,
                class: null,
                parameters: [
                    'sqlite:' . ROOT_DIR . '/private/db/smolcms.sq3',
                ]
            ),
            new Service(
                identifier: ApplicationStartupHandler::class,
                parameters: [
                    RegisterSessionHandlerStartupAction::class,
                    ResumeSessionStartupAction::class,
                ]
            )
        ];

        foreach ($services as $service) {
            $this->services[$service->getIdentifier()] = $service;
        }
    }

    public function getServiceByIdentifier(string $identifier): ?Service
    {
        return $this->services[$identifier] ?? null;
    }

    /**
     * @param Service $service
     * @param bool $replace Whether or not to replace existing service.
     *                      If this is false (default) and the service id already exists, an exception will be thrown.
     * @throws ServiceConflictException
     */
    public function addService(Service $service, bool $replace = false): void
    {
        if (!$replace && isset($this->services[$service->getIdentifier()])) {
            throw new ServiceConflictException(
                "Service {$service->getIdentifier()} is already set and service replacement is disabled."
            );
        }
        $this->services[$service->getIdentifier()] = $service;
    }
}