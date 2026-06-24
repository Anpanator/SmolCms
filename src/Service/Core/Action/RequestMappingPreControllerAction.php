<?php

declare(strict_types=1);

namespace SmolCms\Service\Core\Action;

use SmolCms\Data\Request\Request;
use SmolCms\Data\Response\Response;
use SmolCms\Service\Core\RequestMapper;

final readonly class RequestMappingPreControllerAction implements PreControllerAction
{
    public function __construct(
        private RequestMapper $requestMapper,
    )
    {
    }

    public function process(Request $request, object $controller, string $handler, array &$handlerArguments): ?Response
    {
        $handlerArguments['request'] = $this->requestMapper->mapRequest($request, $controller, $handler);
        return null;
    }
}
