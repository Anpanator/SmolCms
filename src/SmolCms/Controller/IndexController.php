<?php

declare(strict_types=1);

namespace SmolCms\Controller;


use SmolCms\Data\Request\Request;
use SmolCms\Data\Response\Response;
use SmolCms\Service\Core\TemplateService;
use SmolCms\Template\BaseTemplate;

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
        return $this->templateService->generateResponse(BaseTemplate::class,
            [
                'pageTitle' => 'Nice Boat',
                'articleContent' => $request->getUrl(),
                'pageLanguage' => 'en',
            ]
        );
    }

    public function postAction(Request $request): Response
    {
        return new Response(status: 200, content: print_r($request, true));
    }
}