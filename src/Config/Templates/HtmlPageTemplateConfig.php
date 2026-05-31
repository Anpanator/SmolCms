<?php
declare(strict_types=1);

namespace SmolCms\Config\Templates;

use SmolCms\Template\Component\HeadComponent;
use SmolCms\Template\Component\MainNavigationComponent;
use SmolCms\Template\HtmlTemplate;

readonly class HtmlPageTemplateConfig implements TemplateConfig
{
    public function __construct(
        private string $language,
        private array  $navigationRoutes,
        private string $pageTitle,
        private string $contentClass,
        private array  $contentParams,
    )
    {
    }

    public function getConfig(): array
    {
        return [
            HtmlTemplate::class => [
                HeadComponent::class => [$this->pageTitle],
                MainNavigationComponent::class => [$this->navigationRoutes],
                $this->contentClass => $this->contentParams,
                $this->language,
            ]
        ];
    }
}
