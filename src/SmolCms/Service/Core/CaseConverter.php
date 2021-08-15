<?php
declare(strict_types=1);

namespace SmolCms\Service\Core;


use InvalidArgumentException;

class CaseConverter
{
    /**
     * Converts from snake case (e.g. hello_world) to camel case (e.g. helloWorld).
     * This function may return unexpected results with strings containing multibyte characters.
     *
     * @param string $original
     * @return string
     */
    public function snakeCaseToCamelCase(string $original): string
    {
        $strParts = explode('_', strtolower($original));
        $new = '';
        foreach ($strParts as $key => $part) {
            if ($key !== 0) {
                $part[0] = strtoupper($part[0]);
            }
            $new .= $part;
        }
        return $new;
    }

    /**
     * Converts from camel case (e.g. helloWorld) to snake case (e.g. hello_world).
     * This function may return unexpected results with strings containing multibyte characters.
     *
     * @param string $original
     * @return string
     */
    public function camelCaseToSnakeCase(string $original): string
    {
        $splitString = preg_split(
            pattern: "/([A-Z][^A-Z]*)/",
            subject: $original,
            flags: PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY
        );
        if ($splitString === false) {
            throw new InvalidArgumentException("Could not convert $original to snake case.");
        }
        return strtolower(implode('_', $splitString));
    }
}