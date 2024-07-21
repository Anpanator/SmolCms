<?php
declare(strict_types=1);

namespace SmolCms\Controller;

use RuntimeException;
use SmolCms\Data\Request\LoginRequest;
use SmolCms\Data\Response\Response;
use SmolCms\Service\Core\TemplateService;

class LoginController
{
    public function __construct(
        private TemplateService $templateService
    )
    {
    }


    public function postAction(LoginRequest $request): Response
    {
        throw new RuntimeException("Not Implemented");
    }
}