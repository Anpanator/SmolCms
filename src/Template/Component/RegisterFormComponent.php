<?php
declare(strict_types=1);

namespace SmolCms\Template\Component;

use SmolCms\Data\Constant\HttpMethod;
use SmolCms\Data\Constant\RouteEnum;
use SmolCms\Template\Template;

readonly class RegisterFormComponent implements Template
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
            <input type="email" name="email">
            <input type="password" name="password">
            <input type="password" name="passwordRepeat">
            <input type="text" name="displayName">
            <input type="text" name="loginName">
            <button type="submit">Register</button>
        </form>
        HTML;
    }
}
