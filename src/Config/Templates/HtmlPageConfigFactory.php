<?php
declare(strict_types=1);

namespace SmolCms\Config\Templates;

readonly class HtmlPageConfigFactory
{
    public function __construct(private HtmlPageDataProvider $provider)
    {
    }

    public function wrap(TemplateConfig $contentConfig): TemplateConfig
    {
        $raw = $contentConfig->getConfig();
        $contentClass = array_key_first($raw);
        return new HtmlPageTemplateConfig(
            language: $this->provider->getLanguage(),
            navigationRoutes: $this->provider->getNavigationRoutes(),
            pageTitle: $this->provider->getPageTitle(),
            contentClass: $contentClass,
            contentParams: $raw[$contentClass],
        );
    }
}
