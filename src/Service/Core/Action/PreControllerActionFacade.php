<?php

declare(strict_types=1);

namespace SmolCms\Service\Core\Action;

use SmolCms\Data\Request\Request;
use SmolCms\Data\Response\Response;

final readonly class PreControllerActionFacade
{
    /** @var PreControllerAction[] */
    private array $actions;

    public function __construct(
        PreControllerAction ...$actions
    )
    {
        $this->actions = $actions;
    }

    public function process(Request $request, object $controller, string $handler, array $handlerArguments): Response
    {
        foreach ($this->actions as $action) {
            $earlyResponse = $action->process($request, $controller, $handler, $handlerArguments);
            if ($earlyResponse !== null) {
                return $earlyResponse;
            }
        }

        return $controller->{$handler}(...$handlerArguments);
    }
}
