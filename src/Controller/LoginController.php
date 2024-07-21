<?php
declare(strict_types=1);

namespace SmolCms\Controller;

use SmolCms\Config\Templates\ArticleTemplateConfig;
use SmolCms\Data\Request\LoginRequest;
use SmolCms\Data\Response\Response;
use SmolCms\Service\Core\TemplateService;

readonly class LoginController
{
    public function __construct(
        private TemplateService $templateService
    )
    {
    }
    public function postAction(LoginRequest $request): Response
    {
        return $this->templateService->generateResponse(
            new ArticleTemplateConfig(
                language: "en",
                pageTitle: "SmolCms Login",
                articleContent: print_r($request, true),
            )
        );
    }
}