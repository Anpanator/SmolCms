<?php

declare(strict_types=1);

namespace SmolCms\Controller;

use SmolCms\Data\Constant\HttpContentType;
use SmolCms\Data\Constant\HttpHeader;
use SmolCms\Data\Request\EmptyRequest;
use SmolCms\Data\Response\Response;
use SmolCms\Service\Asset\AssetService;

readonly class IndexController
{
    public function __construct(
        private AssetService $assetService,
    )
    {
    }

    public function getAction(EmptyRequest $request): Response
    {
        return new Response(
            content: $this->assetService->getHtmlShell(),
            headers: [
                HttpHeader::CONTENT_TYPE->value => HttpContentType::HTML_UTF_8->value,
            ],
        );
    }
}
