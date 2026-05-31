<?php
declare(strict_types=1);

namespace SmolCms\Controller;

use SmolCms\Config\Templates\ArticleTemplateConfig;
use SmolCms\Config\Templates\HtmlPageConfigFactory;
use SmolCms\Data\Constant\ContextKey;
use SmolCms\Data\Constant\HttpStatus;
use SmolCms\Data\Request\Request;
use SmolCms\Data\Response\Response;
use SmolCms\Service\Core\ContextService;
use SmolCms\Service\Core\TemplateService;

readonly class IndexController
{
    public function __construct(
        private TemplateService       $templateService,
        private HtmlPageConfigFactory $htmlPageConfigFactory,
        private ContextService        $contextService,
    )
    {
    }

    public function getAction(Request $request): Response
    {
        $this->contextService->setContext(ContextKey::PAGE_TITLE, "Nice Boat");
        return $this->templateService->generateResponse(
            $this->htmlPageConfigFactory->wrap(
                contentConfig: new ArticleTemplateConfig(articleContent: "Fancy ass content"),
            )
        );
    }

    public function postAction(Request $request): Response
    {
        return new Response(status: HttpStatus::OK, content: print_r($request, true));
    }
}
