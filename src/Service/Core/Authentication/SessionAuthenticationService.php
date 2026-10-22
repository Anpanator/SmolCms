<?php

declare(strict_types=1);

namespace SmolCms\Service\Core\Authentication;

use SmolCms\Data\DTO\SessionUserData;
use SmolCms\Service\Core\Session\SessionService;

readonly class SessionAuthenticationService
{
    public function __construct(private SessionService $sessionService)
    {
    }

    public function resolve(): ?SessionUserData
    {
        return $this->sessionService->getUserData();
    }
}
