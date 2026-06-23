<?php
declare(strict_types=1);

namespace SmolCms\Controller;

use SmolCms\Config\Templates\HtmlPageConfigFactory;
use SmolCms\Config\Templates\SimpleContentComponentConfig;
use SmolCms\Data\Constant\ContextKey;
use SmolCms\Data\Constant\HttpMethod;
use SmolCms\Data\Constant\RouteEnum;
use SmolCms\Data\DTO\SessionUserData;
use SmolCms\Data\Request\Request;
use SmolCms\Data\Request\UserSettingsUpdateRequest;
use SmolCms\Data\Response\RedirectResponse;
use SmolCms\Data\Response\Response;
use SmolCms\Service\Core\ContextService;
use SmolCms\Service\Core\Session\SessionService;
use SmolCms\Service\Core\TemplateService;
use SmolCms\Service\DB\UserService;
use SmolCms\Template\Component\UserSettingsFormComponent;

readonly class UserSettingController
{
    public function __construct(
        private TemplateService       $templateService,
        private HtmlPageConfigFactory $htmlPageConfigFactory,
        private ContextService        $contextService,
        private SessionService        $sessionService,
        private UserService           $userService,
    )
    {
    }

    public function getAction(Request $request): Response
    {
        $this->contextService->setContext(ContextKey::PAGE_TITLE, 'Settings');
        $userData = $this->sessionService->getUserData();

        return $this->templateService->generateResponse(
            $this->htmlPageConfigFactory->wrap(
                new SimpleContentComponentConfig(
                    content: 'User settings',
                    extraComponents: $userData !== null
                        ? [UserSettingsFormComponent::class => [RouteEnum::SETTINGS, HttpMethod::POST, $userData->displayName]]
                        : [],
                ),
            )
        );
    }

    public function postAction(UserSettingsUpdateRequest $request): Response
    {
        $userData = $this->sessionService->getUserData();
        $user = $this->userService->findOneById($userData->id);
        $user->setDisplayName($request->displayName);
        $this->userService->saveOrUpdate($user);
        $this->sessionService->setUserData(
            new SessionUserData($user->getId(), $user->getDisplayName())
        );

        return new RedirectResponse(RouteEnum::SETTINGS);
    }
}
