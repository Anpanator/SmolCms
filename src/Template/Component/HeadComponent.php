<?php
declare(strict_types=1);

namespace SmolCms\Template\Component;


use SmolCms\Template\Template;

readonly class HeadComponent implements Template
{

    public function __construct(
        private string $pageTitle
    )
    {
    }

    public function render(): string
    {
        return <<<HTML
        <head>
            <title>$this->pageTitle</title>
            <link type="text/css" rel="stylesheet" href="/public/css/main.css">
        </head>
        HTML;
    }
}