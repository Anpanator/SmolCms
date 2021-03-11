<?php
declare(strict_types=1);

namespace SmolCms\Service\Url;


use InvalidArgumentException;

class PathParamMappingService
{
    /**
     * @param string $urlPath
     * @param string $routePattern
     * @return string[]
     */
    public function getPathParamsByUrlPathAndRoutePattern(string $urlPath, string $routePattern): array
    {
        $pathSeparator = '/';
        $pathParts = explode($pathSeparator, trim($urlPath, $pathSeparator)) ?: null;
        $routeParts = explode($pathSeparator, trim($routePattern, $pathSeparator)) ?: null;

        if (count($pathParts) !== count($routeParts)) {
            throw new InvalidArgumentException('Supplied routePattern and path must have the same amount of elements.');
        }
        $paramValues = [];
        foreach ($routeParts as $i => $routePart) {
            if (($routePart[0] ?? '') !== '{') {
                continue;
            }
            $paramName = trim($routePart, '{}');
            $paramValues[$paramName] = $pathParts[$i];
        }

        return $paramValues;
    }
}