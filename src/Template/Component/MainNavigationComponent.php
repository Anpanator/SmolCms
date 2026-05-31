<?php
declare(strict_types=1);

namespace SmolCms\Template\Component;

use SmolCms\Data\Constant\RouteEnum;
use SmolCms\Template\Template;

readonly class MainNavigationComponent implements Template
{
    /** @param RouteEnum[] $routes */
    public function __construct(private array $routes)
    {
    }

    public function render(): string
    {
        $items = '';
        foreach ($this->routes as $route) {
            $label = $route->name;
            $items .= "<li><a href=\"{$route->value}\">{$label}</a></li>";
        }
        return <<<HTML
        <nav id="main-nav"><ul>{$items}</ul></nav>
        HTML;
    }
}
