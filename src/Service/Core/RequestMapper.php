<?php
declare(strict_types=1);

namespace SmolCms\Service\Core;

use ReflectionClass;
use ReflectionException;
use RuntimeException;
use SmolCms\Data\Request\Request;
use SmolCms\Data\Request\ValidatedRequest;
use SmolCms\Exception\BadRequestException;
use TypeError;

readonly class RequestMapper
{
    const string RAW_REQUEST_PARAM_NAME = 'rawRequest';

    public function __construct()
    {
    }

    /**
     * @param Request $request
     * @param object $controller
     * @param string $handlerMethodName
     * @return ValidatedRequest|Request
     * @throws ReflectionException
     *
     * Maps POST and GET params into a custom data object and validates it.
     * Scans the passed handler method for parameters extending the ValidatedRequest class and maps request parameters
     * of the same name into it.
     *
     * POST parameters have priority when there are naming conflicts with GET parameters.
     *
     * If the handler method has a parameter of the type Request (which contains raw data), it will not map anything
     * and instead return the unmapped request object that was passed to it.
     */
    public function mapRequest(Request $request, object $controller, string $handlerMethodName): ValidatedRequest|Request
    {
        $refController = new ReflectionClass($controller);
        $refParams = $refController->getMethod($handlerMethodName)?->getParameters() ?? [];
        $paramTypeToMapRequest = null;
        $refClassOfClassToMap = null;
        foreach ($refParams as $refParam) {
            $paramType = $refParam->getType();
            if (!$paramType) {
                continue;
            }
            // If a plain Request parameter is found, no mapping needs to be done
            if ($paramType->getName() === Request::class) {
                return $request;
            }
            $refClassOfClassToMap = new ReflectionClass($refParam->getType()->getName());
            $topAncestor = $this->getTopAncestor($refClassOfClassToMap);
            if ($topAncestor->getName() !== ValidatedRequest::class) {
                continue;
            }
            $paramTypeToMapRequest = $paramType;
        }
        if (!$paramTypeToMapRequest) {
            throw new RuntimeException("No Request parameter found for {$refController->getName()}::{$handlerMethodName}");
        }

        if (!$refClassOfClassToMap->isReadOnly()) {
            throw new RuntimeException("Request class {$paramTypeToMapRequest->getName()} to be mapped must be declared readonly");
        }

        $paramsToMap = $refClassOfClassToMap->getConstructor()?->getParameters();
        $mappedParams = [self::RAW_REQUEST_PARAM_NAME => $request];
        foreach ($paramsToMap as $paramToMap) {
            $name = $paramToMap->getName();
            if ($name === self::RAW_REQUEST_PARAM_NAME) {
                continue;
            }
            // POST params have higher priority in case of name conflicts
            $mappedParams[$name] = $request->postParams[$name] ?? $request->getParams[$name] ?? null;
        }
        $class = $refClassOfClassToMap->getName();
        try {
            $mappedRequest = new $class(...$mappedParams);
        } catch (TypeError $e) {
            throw new BadRequestException(
                "Could not map request parameters to {$class} for {$refController->getName()}::{$handlerMethodName}",
                0,
                $e
            );
        }
        return $mappedRequest;
    }

    private function getTopAncestor(ReflectionClass $reflectionClass): ReflectionClass
    {
        $parentClass = $reflectionClass->getParentClass();
        return $parentClass
            ? $this->getTopAncestor($parentClass)
            : $reflectionClass;
    }
}