<?php

declare(strict_types=1);

namespace SmolCms\Service\Core\Action;

use SmolCms\Data\Constant\HttpMethod;
use SmolCms\Data\Constant\HttpStatus;
use SmolCms\Data\Request\Request;
use SmolCms\Data\Response\Response;
use SmolCms\Service\Core\CorsService;

final readonly class SameOriginPreControllerAction implements PreControllerAction
{
    public function __construct(
        private CorsService $corsService,
    )
    {
    }

    public function process(Request $request, object $controller, string $handler, array &$handlerArguments): ?Response
    {
        if (!in_array($request->method, [HttpMethod::POST, HttpMethod::PUT, HttpMethod::DELETE], true)) {
            return null;
        }

        if (!$this->corsService->isCrossOrigin($request)) {
            return null;
        }

        if ($this->corsService->isCrossOriginApiKeyRequest($request, $controller, $handler)) {
            return null;
        }

        return new Response(HttpStatus::FORBIDDEN);
    }
}
