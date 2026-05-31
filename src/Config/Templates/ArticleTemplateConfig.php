<?php
declare(strict_types=1);

namespace SmolCms\Config\Templates;

use SmolCms\Template\Component\ArticleComponent;

readonly class ArticleTemplateConfig implements TemplateConfig
{
    public function __construct(private string $articleContent)
    {
    }

    public function getConfig(): array
    {
        return [ArticleComponent::class => [$this->articleContent]];
    }
}
