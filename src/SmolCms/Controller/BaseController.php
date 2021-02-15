<?php

declare(strict_types=1);

namespace SmolCms\Controller\Interfaces;


use SmolCms\Data\Request\Request;
use SmolCms\Data\Response\Response;

abstract class BaseController implements Routable
{
    public function getAction(Request $request): Response
    {
        // TODO: Implement getAction() method.
    }

    public function postAction(Request $request): Response
    {
        // TODO: Implement postAction() method.
    }

    public function putAction(Request $request): Response
    {
        // TODO: Implement putAction() method.
    }

    public function deleteAction(Request $request): Response
    {
        // TODO: Implement deleteAction() method.
    }

    public function optionsAction(Request $request): Response
    {
        // TODO: Implement optionsAction() method.
    }
}