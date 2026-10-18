<?php
declare(strict_types=1);

namespace SmolCms\Controller;

use SmolCms\Data\Constant\AccessLevel;
use SmolCms\Data\Constant\HttpStatus;
use SmolCms\Data\DTO\SessionUserData;
use SmolCms\Data\Request\LoginRequest;
use SmolCms\Data\Request\Request;
use SmolCms\Data\Response\JsonResponse;
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
        $sessionUserData = new SessionUserData(
            $user->getId(),
            $user->getDisplayName(),
            $user->getAccessLevel(),
        );
        $this->sessionService->setUserData($sessionUserData);

        return new JsonResponse(['user' => $this->serializeUser($sessionUserData)]);
    }

    public function getSessionAction(Request $request): Response
    {
        $userData = $this->sessionService->getUserData();

        return new JsonResponse(
            ['user' => $userData === null ? null : $this->serializeUser($userData)]
        );
    }

    public function logoutAction(Request $request): Response
    {
        $this->sessionService->destroySession();
        return new JsonResponse(status: HttpStatus::NO_CONTENT);
    }

    private function serializeUser(SessionUserData $userData): array
    {
        return [
            'id' => $userData->id,
            'displayName' => $userData->displayName,
            'accessLevel' => $userData->accessLevel->value,
        ];
    }
}
