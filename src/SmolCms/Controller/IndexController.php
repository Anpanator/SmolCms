<?php

declare(strict_types=1);

namespace SmolCms\Controller;


use SmolCms\Data\Request\Request;
use SmolCms\Data\Response\Response;

class IndexController
{
    public function getAction(Request $request): Response
    {
        return new Response(status: 200, content: print_r($request, true));
    }

    public function postAction(Request $request): Response
    {
        return new Response(status: 200, content: print_r($request, true));
    }

}