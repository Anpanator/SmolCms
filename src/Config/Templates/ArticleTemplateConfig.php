<?php
declare(strict_types=1);

namespace SmolCms\Config\Templates;

use SmolCms\Data\Constant\HttpMethod;
use SmolCms\Data\Constant\RouteEnum;
use SmolCms\Template\Component\ArticleComponent;
use SmolCms\Template\Component\LoginFormComponent;

readonly class ArticleTemplateConfig implements TemplateConfig
{
    public function __construct(
        private string $articleContent,
        private bool   $showLoginForm = false,
    )
    {
    }

    public function getConfig(): array
    {
        return [
            ArticleComponent::class => [
                $this->articleContent,
                ...$this->showLoginForm ? [LoginFormComponent::class => [RouteEnum::LOGIN, HttpMethod::POST]] : [],
            ],
        ];
    }
}
