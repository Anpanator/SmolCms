<?php
declare(strict_types=1);

namespace SmolCms\Controller;

use SmolCms\Data\Constant\HttpStatus;
use SmolCms\Data\DTO\SessionUserData;
use SmolCms\Data\Request\LoginRequest;
use SmolCms\Data\Request\Request;
use SmolCms\Data\Response\RedirectResponse;
use SmolCms\Data\Response\Response;
use SmolCms\Service\Core\Authentication\AuthenticationService;
use SmolCms\Service\Core\Session\SessionService;
use SmolCms\Service\DB\UserService;

readonly class AuthController
{
    public function __construct(
        private AuthenticationService $authenticationService,
        private SessionService        $sessionService,
        private UserService $userService,
    )
    {
    }

    public function postAction(LoginRequest $request): Response
    {
        $user = $this->userService->findOneByLoginName($request->loginName);
        if ($user === null) {
            return new Response(HttpStatus::UNAUTHORIZED);
        }

        $authenticated = $this->authenticationService->authenticate($request->password, $user);
        if ($authenticated === false) {
            return new Response(HttpStatus::UNAUTHORIZED);
        }

        $this->sessionService->startSession();
        $this->sessionService->setUserData(
            new SessionUserData(
                $user->getId(),
                $user->getDisplayName()
            )
        );

        return new RedirectResponse();
    }

    public function logoutAction(Request $request): Response
    {
        $this->sessionService->destroySession();
        return new RedirectResponse();
    }
}