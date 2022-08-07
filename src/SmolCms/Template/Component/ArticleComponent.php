<?php
declare(strict_types=1);

namespace SmolCms\Template\Component;

use SmolCms\Template\Template;

class ArticleComponent implements Template
{
    public function __construct(
        private readonly string $contentSlot
    )
    {
    }

    public function render(): string
    {
        return <<<HTML
        <article>$this->contentSlot</article>
        HTML;
    }
}