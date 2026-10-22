<?php

declare(strict_types=1);

namespace SmolCms\Service\Core\Action;

use ReflectionMethod;
use SmolCms\Data\Constant\ContextKey;
use SmolCms\Data\Constant\HttpStatus;
use SmolCms\Data\DTO\SessionUserData;
use SmolCms\Data\Request\Request;
use SmolCms\Data\Response\Response;
use SmolCms\Service\Core\Authentication\ApiKeyAuthenticationService;
use SmolCms\Service\Core\Authentication\SessionAuthenticationService;
use SmolCms\Service\Core\Attribute\Authenticated;
use SmolCms\Service\Core\ContextService;
use SmolCms\Service\Core\Session\SessionService;
use SmolCms\Service\DB\UserService;

final readonly class AuthorizationPreControllerAction implements PreControllerAction
{
    public function __construct(
        private SessionAuthenticationService $sessionAuthenticationService,
        private ApiKeyAuthenticationService $apiKeyAuthenticationService,
        private SessionService $sessionService,
        private UserService $userService,
        private ContextService $contextService,
    )
    {
    }

    public function process(Request $request, object $controller, string $handler, array &$handlerArguments): ?Response
    {
        $this->contextService->setContext(ContextKey::AUTHENTICATED_VIA_API, false);

        $reflectionMethod = new ReflectionMethod($controller, $handler);
        $attributes = $reflectionMethod->getAttributes(Authenticated::class);

        if (empty($attributes)) {
            return null;
        }

        $authenticated = $attributes[0]->newInstance();
        $requiredAccessLevel = $authenticated->accessLevel;

        $userData = $this->sessionAuthenticationService->resolve();
        $authenticatedViaApi = false;

        if ($userData === null && $authenticated->apiEnabled) {
            $userData = $this->apiKeyAuthenticationService->resolve($request);
            $authenticatedViaApi = $userData !== null;
        }

        if ($userData === null) {
            return new Response(HttpStatus::UNAUTHORIZED);
        }

        $user = $this->userService->findOneById($userData->id);
        if ($user === null) {
            if (!$authenticatedViaApi) {
                $this->sessionService->destroySession();
            }
            return new Response(HttpStatus::FORBIDDEN);
        }

        $accessLevel = $user->getAccessLevel();
        $this->sessionService->setUserData(new SessionUserData(
            $userData->id,
            $user->getDisplayName(),
            $accessLevel,
        ));
        $this->contextService->setContext(ContextKey::AUTHENTICATED_VIA_API, $authenticatedViaApi);

        if ($accessLevel->value < $requiredAccessLevel->value) {
            return new Response(HttpStatus::FORBIDDEN);
        }

        return null;
    }
}
