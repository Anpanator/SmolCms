<?php
declare(strict_types=1);

namespace SmolCms\Controller;

use DateTime;
use SmolCms\Config\Templates\HtmlPageConfigFactory;
use SmolCms\Config\Templates\SimpleContentComponentConfig;
use SmolCms\Data\Constant\ContextKey;
use SmolCms\Data\Constant\HttpMethod;
use SmolCms\Data\Constant\HttpStatus;
use SmolCms\Data\Constant\RouteEnum;
use SmolCms\Data\Persistence\UserEntity;
use SmolCms\Data\Request\RegisterRequest;
use SmolCms\Data\Request\Request;
use SmolCms\Data\Response\AuthResponse;
use SmolCms\Data\Response\Response;
use SmolCms\Service\Core\ContextService;
use SmolCms\Service\Core\Session\SessionService;
use SmolCms\Service\Core\TemplateService;
use SmolCms\Service\DB\UserService;
use SmolCms\Template\Component\RegisterFormComponent;

readonly class RegisterController
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
        $this->contextService->setContext(ContextKey::PAGE_TITLE, 'Register');
        return $this->templateService->generateResponse(
            $this->htmlPageConfigFactory->wrap(
                new SimpleContentComponentConfig(
                    content: 'Create a new account',
                    extraComponents: $this->sessionService->getUserData() === null
                        ? [RegisterFormComponent::class => [RouteEnum::REGISTER, HttpMethod::POST]]
                        : [],
                ),
            )
        );
    }

    public function postAction(RegisterRequest $request): Response
    {
        if ($request->password !== $request->passwordRepeat) {
            return new Response(HttpStatus::BAD_REQUEST);
        }

        $existingUser = $this->userService->findOneByLoginName($request->loginName);
        if ($existingUser !== null) {
            return new Response(HttpStatus::CONFLICT);
        }

        $user = new UserEntity(
            id: null,
            loginName: $request->loginName,
            password: password_hash($request->password, PASSWORD_DEFAULT),
            displayName: $request->displayName,
            state: 'active',
            registerDate: new DateTime(),
            lastLoginDate: null,
        );

        $this->userService->saveOrUpdate($user);

        return new AuthResponse(RouteEnum::LOGIN);
    }
}
