<?php
declare(strict_types=1);

namespace SmolCms\Config\Templates;

use SmolCms\Template\Component\ArticleComponent;
use SmolCms\Template\Component\HeadComponent;
use SmolCms\Template\HtmlTemplate;

readonly class ArticleTemplateConfig implements TemplateConfig
{

    public function __construct(
        private string $language,
        private string $pageTitle,
        private string $articleContent
    )
    {
    }

    public function getConfig(): array
    {
        return [
            HtmlTemplate::class => [
                HeadComponent::class => [$this->pageTitle],
                ArticleComponent::class => [$this->articleContent],
                $this->language
            ]
        ];
    }
}