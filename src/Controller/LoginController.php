<?php
declare(strict_types=1);

namespace SmolCms\Controller;

use SmolCms\Data\Constant\HttpStatus;
use SmolCms\Data\Request\LoginRequest;
use SmolCms\Data\Response\Response;
use SmolCms\Service\Core\Authentication\AuthenticationService;
use SmolCms\Service\Core\Session\SessionService;

readonly class LoginController
{
    public function __construct(
        private AuthenticationService $authenticationService,
        private SessionService        $sessionService,
    )
    {
    }

    public function postAction(LoginRequest $request): Response
    {
        $authenticated = $this->authenticationService->authenticate($request->loginName, $request->password);
        if ($authenticated === false) {
            return new Response(HttpStatus::UNAUTHORIZED);
        }
        // TODO: Set session data
        // $this->sessionService->startSession();

        // TODO: Set location header to redirect to some page after login
        return new Response();
    }
}