<?php

declare(strict_types=1);

namespace SmolCms\Service\Core;


use SmolCms\Data\Constant\HttpStatus;
use SmolCms\Data\Request\Request;
use SmolCms\Data\Response\Response;
use SmolCms\Service\Factory\UrlFactory;

class ApplicationCore
{
    private ServiceBuilder $serviceBuilder;
    private Router $router;
    private UrlFactory $urlFactory;

    /**
     * ApplicationCore constructor.
     * @param ServiceBuilder $serviceBuilder
     */
    public function __construct(ServiceBuilder $serviceBuilder)
    {
        $this->serviceBuilder = $serviceBuilder;
        $this->router = $this->serviceBuilder->getService(Router::class);
        $this->urlFactory = $this->serviceBuilder->getService(UrlFactory::class);
    }

    public function run(): void
    {
        $url = $this->urlFactory->createUrlFromUrlString($this->getRequestUrl());
        $requestMethod = $_SERVER['REQUEST_METHOD'];
        $route = $this->router->getRouteByUrlAndMethod($url, $requestMethod);
        $response = null;
        if (!$route) {
            $response = $this->generateDefaultResponse();
            $this->output($response);
            return;
        } else {
            $controller = $this->serviceBuilder->getService($route->getController());
            $response = $controller?->{$route->getHandler()}(new Request());
            if (!$response) {
                $response = $this->generateDefaultResponse();
            }
        }
        $this->output($response);
    }

    private function getRequestUrl(): string
    {
        return ($_SERVER['HTTPS'] ?? '' === 'on' ? "https" : "http")
            . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
    }

    private function generateDefaultResponse(): Response
    {
        return new Response(HttpStatus::NOT_FOUND);
    }

    private function output(Response $response)
    {
        http_response_code($response->getStatus());
        echo $response->getContent();
    }
}