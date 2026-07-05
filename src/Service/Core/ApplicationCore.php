<?php

declare(strict_types=1);

namespace SmolCms\Service\Core;


use SmolCms\Data\Constant\HttpStatus;
use SmolCms\Data\Request\Request;
use SmolCms\Data\Response\Response;
use SmolCms\Service\Core\Action\PreControllerActionFacade;
use SmolCms\Service\Factory\RequestFactory;
use SmolCms\Service\Url\PathParamMappingService;
use Throwable;

readonly class ApplicationCore
{
    public function __construct(
        private ServiceBuilder            $serviceBuilder,
        private ApplicationStartupHandler $startupHandler,
        private Router                    $router,
        private RequestFactory            $requestFactory,
        private PathParamMappingService   $pathParamMappingService,
        private PreControllerActionFacade $preControllerActionFacade,
        private ExceptionResponseService  $exceptionResponseService,
        private CliCommandHandler         $cliCommandHandler,
    )
    {
    }

    public function init(): void
    {
        $this->startupHandler->runActions();
    }

    public function runCli(): void
    {
        $ran = $this->cliCommandHandler->runCommand();
        if ($ran) {
            exit(0);
        }
    }

    public function runCgi(): void
    {
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
        $handler = $route->getHandlerOrDefault();
        try {
            $response = $this->preControllerActionFacade->process($request, $controller, $handler, $handlerArguments);
        } catch (Throwable $e) {
            return $this->exceptionResponseService->createResponseFromException($e);
        }
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