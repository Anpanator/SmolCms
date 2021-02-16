<?php

declare(strict_types=1);

namespace SmolCms\Service\Core;

//TODO: Use optional configuration to build service
//TODO: Handle scalar/mixed types in constructor. Require configuration?
//TODO: Register services somewhere and reuse instances
use Exception;
use InvalidArgumentException;
use ReflectionClass;
use ReflectionException;
use ReflectionNamedType;

class ServiceBuilder
{
    /**
     * @param string $class
     * @return object
     * @throws ReflectionException
     * @throws Exception
     */
    public function build(string $class): object
    {
        if (!class_exists($class)) {
            throw new InvalidArgumentException('Cannot find class to build');
        }

        $reflector = new ReflectionClass($class);
        $refConstructor = $reflector->getConstructor();

        if (!$refConstructor || $refConstructor->getNumberOfParameters() === 0) {
            return new $class();
        }

        $constructorParams = $refConstructor->getParameters();
        $builtParameters = [];
        foreach ($constructorParams as $param) {
            $paramType = $param->getType();
            if (!($paramType instanceof ReflectionNamedType)) {
                throw new Exception("Unknown case when trying to build service");
            }
            /** @var ReflectionNamedType $paramType */
            $builtParameters[$param->getPosition()] = $this->build($paramType->getName());
        }
        ksort($builtParameters);
        return new $class(...$builtParameters);
    }
}