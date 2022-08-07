<?php
declare(strict_types=1);

namespace SmolCms\Template;


class HtmlTemplate implements Template
{
    public function __construct(
        private readonly Template $headSlot,
        private readonly Template $contentSlot,
        private readonly string   $language,
    )
    {
    }

    public function render(): string
    {
        return <<<HTML
        <html lang="$this->language">
            {$this->headSlot->render()}           
            <body> 
                {$this->contentSlot->render()}
            </body>
        </html>
        HTML;
    }
}