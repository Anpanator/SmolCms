<?php

declare(strict_types=1);

namespace SmolCms\Controller;

use SmolCms\Data\Constant\HttpHeader;
use SmolCms\Data\Request\Request;
use SmolCms\Data\Response\Response;

readonly class IndexController
{
    private const HTML_SHELL = <<<'HTML'
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>SmolCms</title>
    <link rel="stylesheet" href="/public/css/main.css">
</head>
<body>
    <div id="app"></div>
    <script type="module" src="/public/js/main.js"></script>
</body>
</html>
HTML;

    public function getAction(Request $request): Response
    {
        return new Response(
            content: self::HTML_SHELL,
            headers: [
                HttpHeader::CONTENT_TYPE->value => 'text/html; charset=UTF-8',
            ],
        );
    }
}
