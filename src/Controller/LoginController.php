<?php
declare(strict_types=1);

namespace SmolCms\Controller;

use SmolCms\Config\Templates\HtmlPageConfigFactory;
use SmolCms\Config\Templates\SimpleContentComponentConfig;
use SmolCms\Data\Constant\ContextKey;
use SmolCms\Data\Constant\HttpMethod;
use SmolCms\Data\Constant\RouteEnum;
use SmolCms\Data\Request\Request;
use SmolCms\Data\Response\Response;
use SmolCms\Service\Core\ContextService;
use SmolCms\Service\Core\Session\SessionService;
use SmolCms\Service\Core\TemplateService;
use SmolCms\Template\Component\LoginFormComponent;

readonly class LoginController
{
    public function __construct(
        private TemplateService       $templateService,
        private HtmlPageConfigFactory $htmlPageConfigFactory,
        private ContextService        $contextService,
        private SessionService        $sessionService,
    )
    {
    }

    public function getAction(Request $request): Response
    {
        $this->contextService->setContext(ContextKey::PAGE_TITLE, 'Login');
        return $this->templateService->generateResponse(
            $this->htmlPageConfigFactory->wrap(
                new SimpleContentComponentConfig(
                    content: 'Please log in',
                    extraComponents: $this->sessionService->getUserData() === null
                        ? [LoginFormComponent::class => [RouteEnum::LOGIN, HttpMethod::POST]]
                        : [],
                ),
            )
        );
    }
}
