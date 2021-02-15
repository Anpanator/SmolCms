<?php

declare(strict_types=1);

namespace SmolCms\Controller\Interfaces;


use SmolCms\Data\Request\Request;
use SmolCms\Data\Response\Response;

interface Routable
{
    public function getAction(Request $request): Response;

    public function postAction(Request $request): Response;

    public function putAction(Request $request): Response;

    public function deleteAction(Request $request): Response;

    public function optionsAction(Request $request): Response;
}