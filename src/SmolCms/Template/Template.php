<?php
declare(strict_types=1);

namespace SmolCms\Template;


interface Template
{
    public function render(array $data): string;

    /**
     * Must be implemented by all Templates and return all expected variables as an array of strings.
     *
     * @return string[]
     */
    public static function getTemplateVars(): array;
}