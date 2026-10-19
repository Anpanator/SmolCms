<?php
declare(strict_types=1);

namespace SmolCms\TestUtils;

use SmolCms\Data\Constant\AccessLevel;
use SmolCms\Data\DTO\SessionUserData;
use SmolCms\Service\Core\Session\SessionService;
use SmolCms\Service\DB\UserService;
use SmolCms\TestUtils\Attributes\Autowire;

/**
 * Adds login support for test users whose access level is above NOVICE:
 * FunctionalTestCase::loginUser() always creates NOVICE users, so the user's
 * access level is raised afterwards in the database and in the session data.
 */
trait LoginWithAccessLevel
{
    #[Autowire]
    private UserService $loginWithAccessLevelUserService;

    #[Autowire]
    private SessionService $loginWithAccessLevelSessionService;

    protected function loginUserWithAccessLevel(
        string      $loginName,
        string      $displayName,
        AccessLevel $accessLevel,
    ): void
    {
        $this->loginUser($loginName, $displayName);
        $user = $this->loginWithAccessLevelUserService->findOneByLoginName($loginName);
        if ($user === null) {
            throw new \LogicException("Test user '$loginName' was not created by loginUser().");
        }
        $user->setAccessLevel($accessLevel);
        $this->loginWithAccessLevelUserService->update($user);
        $this->loginWithAccessLevelSessionService->setUserData(new SessionUserData(
            $user->getId(),
            $user->getDisplayName(),
            $user->getAccessLevel(),
        ));
    }
}
