<?php
declare(strict_types=1);

namespace SmolCms\Template\Component;

use SmolCms\Data\Constant\HttpMethod;
use SmolCms\Data\Constant\RouteEnum;
use SmolCms\Template\Template;

readonly class UserSettingsFormComponent implements Template
{

    public function __construct(
        private RouteEnum  $route,
        private HttpMethod $method,
        private string     $currentDisplayName = '',
    )
    {
    }

    public function render(): string
    {
        return <<<HTML
        <form method="{$this->method->value}" action="{$this->route->value}">
            <input type="text" name="displayName" value="{$this->currentDisplayName}">
            <button type="submit">Update</button>
        </form>
        HTML;
    }
}
