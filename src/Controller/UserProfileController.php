<?php
declare(strict_types=1);

namespace SmolCms\Controller;

use SmolCms\Data\Constant\AccessLevel;
use SmolCms\Data\DTO\SessionUserData;
use SmolCms\Data\Request\UserSettingsUpdateRequest;
use SmolCms\Data\Response\JsonResponse;
use SmolCms\Data\Response\Response;
use SmolCms\Service\Core\Attribute\Authenticated;
use SmolCms\Service\Core\Session\SessionService;
use SmolCms\Service\DB\UserService;

readonly class UserProfileController
{
    public function __construct(
        private SessionService $sessionService,
        private UserService    $userService,
    )
    {
    }

    #[Authenticated(AccessLevel::NOVICE)]
    public function postAction(UserSettingsUpdateRequest $request): Response
    {
        $userData = $this->sessionService->getUserData();
        $user = $this->userService->findOneById($userData->id);
        $user->setDisplayName($request->displayName);
        $this->userService->saveOrUpdate($user);
        $updatedUserData = new SessionUserData($user->getId(), $user->getDisplayName(), $user->getAccessLevel());
        $this->sessionService->setUserData($updatedUserData);

        return new JsonResponse(
            [
                'user' => [
                    'id' => $updatedUserData->id,
                    'displayName' => $updatedUserData->displayName,
                    'accessLevel' => $updatedUserData->accessLevel->value,
                ],
            ]
        );
    }
}
