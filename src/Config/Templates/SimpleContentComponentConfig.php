<?php
declare(strict_types=1);

namespace SmolCms\Config\Templates;

use SmolCms\Template\Component\SimpleContentComponent;

readonly class SimpleContentComponentConfig implements TemplateConfig
{
    public function __construct(
        private string $content,
        private array $extraComponents = [],
    )
    {
    }

    public function getConfig(): array
    {
        return [
            SimpleContentComponent::class => [
                $this->content,
                ...$this->extraComponents,
            ],
        ];
    }
}
