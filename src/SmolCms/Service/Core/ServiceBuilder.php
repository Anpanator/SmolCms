<?php

declare(strict_types=1);

namespace SmolCms\Service\Core;

use InvalidArgumentException;
use ReflectionClass;
use ReflectionException;
use ReflectionNamedType;
use SmolCms\Config\ServiceConfiguration;
use SmolCms\Data\Business\Service;
use SmolCms\Data\Business\ServiceRegistry;
use SmolCms\Exception\AutowireException;

class ServiceBuilder
{
    /**
     * ServiceBuilder constructor.
     * @param ServiceConfiguration $serviceConfiguration
     * @param ServiceRegistry $serviceRegistry
     */
    public function __construct(
        private ServiceConfiguration $serviceConfiguration,
        private ServiceRegistry $serviceRegistry
    ) {
        $this->serviceRegistry->addService($this::class, $this);
        $this->serviceRegistry->addService($this->serviceRegistry::class, $this->serviceRegistry);
        $this->serviceRegistry->addService($this->serviceConfiguration::class, $this->serviceConfiguration);
    }

    /**
     * @param string $serviceName
     * @return object
     * @throws ReflectionException
     * @throws InvalidArgumentException
     * @throws AutowireException
     */
    public function getService(string $serviceName): object
    {
        if ($service = $this->serviceRegistry->getService($serviceName)) {
            return $service;
        }
        if ($serviceConfig = $this->serviceConfiguration->getServiceByIdentifier($serviceName)) {
            $service = $this->buildServiceFromConfiguration($serviceConfig);
        } else {
            $service = $this->autowireService($serviceName);
        }
        $this->serviceRegistry->addService($serviceName, $service);
        return $service;
    }

    /**
     * @param Service $service
     * @return object
     * @throws ReflectionException
     */
    private function buildServiceFromConfiguration(Service $service): object
    {
        $class = $service->getClass();
        $builtParameters = array_values($service->getParameters());

        $reflector = new ReflectionClass($class);
        $refConstructor = $reflector->getConstructor();

        if (!$refConstructor || $refConstructor->getNumberOfParameters() === 0) {
            return new $class();
        }

        foreach ($refConstructor->getParameters() as $refParam) {
            $paramType = $refParam->getType();
            if (!($paramType instanceof ReflectionNamedType) || $paramType->isBuiltin()) {
                // In this case we can just take what we have in $builtParameters already
                continue;
            }
            // The constructor parameter is a custom class, so we need to build it
            $builtParameters[$refParam->getPosition()] = $this->getService($builtParameters[$refParam->getPosition()]);
        }
        return new $class(...$builtParameters);
    }

    /**
     * @param string $class
     * @return object
     * @throws ReflectionException
     * @throws InvalidArgumentException
     * @throws AutowireException
     */
    private function autowireService(string $class): object
    {
        if (!class_exists($class)) {
            throw new InvalidArgumentException("Cannot find class to build: $class");
        }

        $reflector = new ReflectionClass($class);
        $refConstructor = $reflector->getConstructor();

        if (!$refConstructor || $refConstructor->getNumberOfParameters() === 0) {
            return new $class();
        }

        $builtParameters = [];
        foreach ($refConstructor->getParameters() as $refParam) {
            $paramType = $refParam->getType();
            if (!($paramType instanceof ReflectionNamedType)) {
                throw new AutowireException("Cannot autowire untyped parameter on class: $class");
            }
            if ($paramType->isBuiltin()) {
                throw new AutowireException(
                    "Cannot autowire builtin type: {$paramType->getName()} on class: $class"
                );
            }
            $builtParameters[$refParam->getPosition()] = $this->getService($paramType->getName());
        }
        ksort($builtParameters);
        return new $class(...$builtParameters);
    }
}