<?php

declare(strict_types=1);

namespace SmolCms\Controller;


use SmolCms\Data\Request\Request;
use SmolCms\Data\Response\Response;
use SmolCms\Service\Core\TemplateService;
use SmolCms\Template\ArticleHtmlTemplate;

class IndexController
{


    /**
     * IndexController constructor.
     * @param TemplateService $templateService
     */
    public function __construct(
        private TemplateService $templateService
    )
    {
    }

    public function getAction(Request $request): Response
    {
        return new Response(status: 200, content: print_r($request, true));
    }

    public function postAction(Request $request): Response
    {
        return new Response(status: 200, content: print_r($request, true));
    }

    public function pathParamAction(Request $request, string $fancyParam, string $coolParam): Response
    {
        return $this->templateService->generateResponse(ArticleHtmlTemplate::class,
            ['content' => $request->getUrl()]
        );
    }

}