<?php
declare(strict_types=1);

namespace SmolCms\Template;


class ArticleHtmlTemplate implements Template
{
    /**
     * SimpleHtmlTemplate constructor.
     * @param HtmlHead $htmlHead
     */
    public function __construct(
        private HtmlHead $htmlHead
    )
    {
    }

    public function render(array $data): string
    {
        $lang = $data['language'] ?? 'en';
        $content = $data['content'] ?? '';
        return <<<HTML
        <html lang="$lang">
            <head>
                {$this->htmlHead->render($data)}           
            </head>
            <body>
                $content
            </body>
        </html>
        HTML;
    }
}