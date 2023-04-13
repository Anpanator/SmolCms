<?php

declare(strict_types=1);

namespace SmolCms\Controller;


use SmolCms\Config\Templates\ArticleTemplateConfig;
use SmolCms\Data\Constant\HttpStatus;
use SmolCms\Data\Request\Request;
use SmolCms\Data\Response\Response;
use SmolCms\Service\Core\TemplateService;

class IndexController
{
    public function __construct(
        private readonly TemplateService $templateService,
    )
    {
    }

    public function getAction(Request $request): Response
    {
        return $this->templateService->generateResponse(
            new ArticleTemplateConfig(
                language: "en",
                pageTitle: "Nice Boat",
                articleContent: "Fancy ass content"
            )
        );
    }

    public function postAction(Request $request): Response
    {
        return new Response(status: HttpStatus::OK, content: print_r($request, true));
    }
}