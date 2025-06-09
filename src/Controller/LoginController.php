<?php
declare(strict_types=1);

namespace SmolCms\Controller;

use SmolCms\Data\Constant\HttpStatus;
use SmolCms\Data\DTO\SessionUserData;
use SmolCms\Data\Request\LoginRequest;
use SmolCms\Data\Response\Response;
use SmolCms\Service\Core\Authentication\AuthenticationService;
use SmolCms\Service\Core\Session\SessionService;
use SmolCms\Service\DB\UserService;

readonly class LoginController
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

        // TODO: Set location header to redirect to some page after login
        return new Response();
    }
}