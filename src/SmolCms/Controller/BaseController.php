<?php

declare(strict_types=1);

namespace SmolCms\Controller;


use SmolCms\Controller\Interfaces\Routable;
use SmolCms\Data\Constant\HttpStatus;
use SmolCms\Data\Request\Request;
use SmolCms\Data\Response\Response;

class BaseController implements Routable
{
    public function getAction(Request $request): Response
    {
        return new Response(HttpStatus::NOT_FOUND);
    }

    public function postAction(Request $request): Response
    {
        return new Response(HttpStatus::NOT_FOUND);
    }

    public function putAction(Request $request): Response
    {
        return new Response(HttpStatus::NOT_FOUND);
    }

    public function deleteAction(Request $request): Response
    {
        return new Response(HttpStatus::NOT_FOUND);
    }

    public function optionsAction(Request $request): Response
    {
        return new Response(HttpStatus::NOT_FOUND);
    }
}