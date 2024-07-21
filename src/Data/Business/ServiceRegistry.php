<?php

declare(strict_types=1);

namespace SmolCms\Data\Business;


use SmolCms\Exception\ServiceConflictException;

class ServiceRegistry
{
    /** @var array<string, object> */
    private array $services;

    public function addService(string $serviceKey, object $service): void
    {
        if (isset($this->services[$serviceKey])) {
            throw new ServiceConflictException('Service is already registered');
        }
        $this->services[$serviceKey] = $service;
    }

    public function getService(string $serviceKey): ?object
    {
        return $this->services[$serviceKey] ?? null;
    }
}