<?php

declare(strict_types=1);

namespace SmolCms\Service\Core;


use SmolCms\Data\Constant\HttpStatus;
use SmolCms\Data\Request\Request;
use SmolCms\Data\Response\Response;
use SmolCms\Service\Factory\RequestFactory;
use SmolCms\Service\Url\PathParamMappingService;

readonly class ApplicationCore
{
    private ServiceBuilder $serviceBuilder;
    private ApplicationStartupHandler $startupHandler;
    private Router $router;
    private RequestFactory $requestFactory;
    private PathParamMappingService $pathParamMappingService;

    public function __construct(ServiceBuilder $serviceBuilder)
    {
        $this->serviceBuilder = $serviceBuilder;
        $this->startupHandler = $this->serviceBuilder->getService(ApplicationStartupHandler::class);
        $this->router = $this->serviceBuilder->getService(Router::class);
        $this->requestFactory = $this->serviceBuilder->getService(RequestFactory::class);
        $this->pathParamMappingService = $this->serviceBuilder->getService(PathParamMappingService::class);
    }

    public function run(): void
    {
        $this->startupHandler->runActions();
        if (PHP_SAPI === 'cli') {
            // TODO: Support cli mode
            return;
        }
        $request = $this->requestFactory->buildRequestFromGlobals();
        $response = $this->handleRequest($request);
        $this->output($response);
    }

    public function simulateRequest(Request $request): Response
    {
        return $this->handleRequest($request);
    }

    private function handleRequest(Request $request): Response
    {
        $route = $this->router->getRouteByUrlAndMethod($request->url, $request->method);
        $response = null;
        if (!$route) {
            return $this->generateDefaultResponse();
        }
        $controller = $this->serviceBuilder->getService($route->controller);
        $handlerArguments = $this->pathParamMappingService->getPathParamsByUrlPathAndRoutePattern(
            urlPath: $request->url->path,
            routePattern: $route->path
        );
        $handlerArguments['request'] = $request;
        $response = $controller?->{$route->getHandlerOrDefault()}(...$handlerArguments);
        if (!$response) {
            $response = $this->generateDefaultResponse();
        }
        return $response;
    }

    private function generateDefaultResponse(): Response
    {
        return new Response(HttpStatus::NOT_FOUND);
    }

    private function output(Response $response): void
    {
        http_response_code($response->getStatus()->value);
        echo $response->getContent();
    }
}