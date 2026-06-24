<?php
declare(strict_types=1);

namespace SmolCms\Template\Component;

use SmolCms\Data\Constant\HttpMethod;
use SmolCms\Data\Constant\RouteEnum;
use SmolCms\Template\Template;

readonly class LoginFormComponent implements Template
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
            <label for="login-name">Login Name</label>
            <input type="text" id="login-name" name="loginName" required minlength="1" maxlength="255">
            <label for="password">Password</label>
            <input type="password" id="password" name="password" required minlength="10" maxlength="72">
            <button type="submit">Login</button>
        </form>
        HTML;
    }
}