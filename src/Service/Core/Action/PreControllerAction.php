<?php

declare(strict_types=1);

namespace SmolCms\Service\Core\Action;

use SmolCms\Data\Request\Request;
use SmolCms\Data\Response\Response;

interface PreControllerAction
{
    /**
     * @param Request $request
     * @param object $controller
     * @param string $handler
     * @param array $handlerArguments
     * @return Response|null Return a Response to short-circuit the chain (e.g. 401). Return null to let the chain continue.
     */
    public function process(Request $request, object $controller, string $handler, array &$handlerArguments): ?Response;
}
