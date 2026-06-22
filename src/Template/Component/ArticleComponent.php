<?php
declare(strict_types=1);

namespace SmolCms\Template\Component;

use SmolCms\Template\Template;

readonly class ArticleComponent implements Template
{
    public function __construct(
        private string    $contentSlot,
        private ?Template $loginForm = null,
    )
    {
    }

    public function render(): string
    {
        $loginFormHtml = $this->loginForm?->render() ?? '';
        return <<<HTML
        <article>$this->contentSlot</article>$loginFormHtml
        HTML;
    }
}