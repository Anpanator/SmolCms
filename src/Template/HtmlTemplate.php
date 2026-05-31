<?php
declare(strict_types=1);

namespace SmolCms\Template;


readonly class HtmlTemplate implements Template
{
    public function __construct(
        private Template $headSlot,
        private Template $navigationSlot,
        private Template $contentSlot,
        private string   $language,
    )
    {
    }

    public function render(): string
    {
        return <<<HTML
        <!DOCTYPE html>
        <html lang="$this->language">
            {$this->headSlot->render()}           
            <body> 
                {$this->navigationSlot->render()}
                {$this->contentSlot->render()}
            </body>
        </html>
        HTML;
    }
}