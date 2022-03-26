<?php

declare(strict_types=1);

namespace SmolCms\Config;


use PDO;
use SmolCms\Data\Business\Service;
use SmolCms\Exception\ServiceConflictException;

class ServiceConfiguration
{
    /** @var array<string, Service> */
    private array $services;

    /**
     * ServiceConfiguration constructor.
     */
    public function __construct()
    {
        $services = [
            new Service(
                identifier: PDO::class,
                class: null,
                parameters: [
                    'mysql:host=localhost;port=3306;dbname=smolcms;charset=utf8mb4',
                    'testuser',
                    'testpw',
                ]
            )
        ];

        foreach ($services as $service) {
            $this->services[$service->getIdentifier()] = $service;
        }
    }

    public function getServices(): array
    {
        return $this->services;
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