<?php

declare(strict_types=1);

namespace SmolCms\Service\Core\Action;

use ReflectionMethod;
use SmolCms\Data\Constant\HttpStatus;
use SmolCms\Data\Request\Request;
use SmolCms\Data\Response\Response;
use SmolCms\Service\Core\Attribute\Authenticated;
use SmolCms\Service\Core\Session\SessionService;

final readonly class AuthorizationPreControllerAction implements PreControllerAction
{
    public function __construct(
        private SessionService $sessionService,
    )
    {
    }

    public function process(Request $request, object $controller, string $handler, array &$handlerArguments): ?Response
    {
        $reflectionMethod = new ReflectionMethod($controller, $handler);
        $attributes = $reflectionMethod->getAttributes(Authenticated::class);

        if (empty($attributes)) {
            return null;
        }

        $authenticated = $attributes[0]->newInstance();
        $requiredAccessLevel = $authenticated->accessLevel;

        $userData = $this->sessionService->getUserData();

        if ($userData === null) {
            return new Response(HttpStatus::FORBIDDEN);
        }

        if ($userData->accessLevel->value < $requiredAccessLevel->value) {
            return new Response(HttpStatus::FORBIDDEN);
        }

        return null;
    }
}
