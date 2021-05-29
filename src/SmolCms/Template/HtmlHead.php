<?php
declare(strict_types=1);

namespace SmolCms\Template;


class HtmlHead implements Template
{
    public function render(array $data): string
    {
        $pageTitle = $data['pageTitle'] ?? '';
        return <<<HTML
            <title>$pageTitle</title>
        HTML;
    }

}