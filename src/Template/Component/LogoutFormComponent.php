<?php
declare(strict_types=1);

namespace SmolCms\Template\Component;

use SmolCms\Data\Constant\HttpMethod;
use SmolCms\Data\Constant\RouteEnum;
use SmolCms\Template\Template;

readonly class LogoutFormComponent implements Template
{

    public function __construct(
        private RouteEnum  $route,
        private HttpMethod $method,
    )
    {
    }

    public function render(): string
    {
        return <<<HTML
        <form method="{$this->method->value}" action="{$this->route->value}">
            <button type="submit">Logout</button>
        </form>
        HTML;
    }
}
