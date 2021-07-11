<?php
declare(strict_types=1);

namespace SmolCms\Template\Component;


use SmolCms\Template\Template;

class HeadComponent implements Template
{
    private const VAR_TITLE = 'pageTitle';

    public function render(array $data): string
    {
        return <<<HTML
            <title>{$data[self::VAR_TITLE]}</title>
            <link type="text/css" rel="stylesheet" href="/public/css/main.css">
        HTML;
    }

    public static function getTemplateVars(): array
    {
        return [self::VAR_TITLE];
    }
}