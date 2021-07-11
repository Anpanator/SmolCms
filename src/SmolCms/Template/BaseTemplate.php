<?php
declare(strict_types=1);

namespace SmolCms\Template;


use SmolCms\Template\Component\ArticleComponent;
use SmolCms\Template\Component\HeadComponent;

class BaseTemplate implements Template
{
    private const VAR_LANG = 'pageLanguage';
    /**
     * BaseTemplate constructor.
     */
    public function __construct(
        private HeadComponent $head,
        private ArticleComponent $article,
    )
    {
    }

    public function render(array $data): string
    {
        return <<<HTML
        <html lang="{$data[self::VAR_LANG]}">
            <head>
                {$this->head->render($data)}           
            </head>
            <body> 
                {$this->article->render($data)}
            </body>
        </html>
        HTML;
    }

    public static function getTemplateVars(): array
    {
        return [self::VAR_LANG];
    }
}