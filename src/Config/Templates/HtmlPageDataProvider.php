<?php
declare(strict_types=1);

namespace SmolCms\Config\Templates;

use SmolCms\Data\Constant\ContextKey;
use SmolCms\Data\Constant\RouteEnum;
use SmolCms\Service\Core\ContextService;

readonly class HtmlPageDataProvider
{
    public function __construct(private ContextService $contextService)
    {
    }

    public function getLanguage(): string
    {
        return 'en';
    }

    public function getPageTitle(): string
    {
        return $this->contextService->getContext(ContextKey::PAGE_TITLE);
    }

    public function getNavigationRoutes(): array
    {
        return [
            'Start' => RouteEnum::START_PAGE,
            'User' => [
                'Login' => RouteEnum::LOGIN,
                'Settings' => RouteEnum::SETTINGS,
                'Logout' => RouteEnum::LOGOUT,
            ],
            'Register' => RouteEnum::REGISTER,
        ];
    }
}
