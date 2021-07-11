<?php
declare(strict_types=1);

namespace SmolCms\Template\Component;


use SmolCms\Template\Template;

class ArticleComponent implements Template
{
    private const VAR_CONTENT = 'articleContent';

    public function render(array $data): string
    {
        return <<<HTML
            <div id="content">
                {$data[self::VAR_CONTENT]}
            </div>
        HTML;
    }

    public static function getTemplateVars(): array
    {
        return [self::VAR_CONTENT];
    }
}