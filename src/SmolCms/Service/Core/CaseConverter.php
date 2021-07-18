<?php
declare(strict_types=1);

namespace SmolCms\Service\Core;


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
}