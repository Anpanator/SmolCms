<?php

declare(strict_types=1);

namespace SmolCms\Service\Core;


use ReflectionException;
use SmolCms\Data\Constant\HttpStatus;
use SmolCms\Data\Request\Request;
use SmolCms\Data\Response\Response;
use SmolCms\Service\Factory\RequestFactory;

class ApplicationCore
{
    private ServiceBuilder $serviceBuilder;
    private Router $router;
    private RequestFactory $requestFactory;

    /**
     * ApplicationCore constructor.
     * @param ServiceBuilder $serviceBuilder
     * @throws ReflectionException
     */
    public function __construct(ServiceBuilder $serviceBuilder)
    {
        $this->serviceBuilder = $serviceBuilder;
        $this->router = $this->serviceBuilder->getService(Router::class);
        $this->requestFactory = $this->serviceBuilder->getService(RequestFactory::class);
    }

    /**
     * @throws ReflectionException
     */
    public function run(): void
    {
        if (PHP_SAPI === 'cli') {
            // TODO: Support cli mode
            return;
        }
        $request = $this->requestFactory->buildRequestFromGlobals();
        $response = $this->handleRequest($request);
        $this->output($response);
    }

    /**
     * @param Request $request
     * @return Response
     * @throws ReflectionException
     */
    public function simulateRequest(Request $request): Response
    {
        return $this->handleRequest($request);
    }

    /**
     * @param Request $request
     * @return Response
     * @throws ReflectionException
     */
    private function handleRequest(Request $request): Response
    {
        $route = $this->router->getRouteByUrlAndMethod($request->getUrl(), $request->getMethod());
        $response = null;
        if (!$route) {
            return $this->generateDefaultResponse();
        } else {
            $controller = $this->serviceBuilder->getService($route->getController());
            $response = $controller?->{$route->getHandler()}($request);
            if (!$response) {
                $response = $this->generateDefaultResponse();
            }
        }
        return $response;
    }

    private function generateDefaultResponse(): Response
    {
        return new Response(HttpStatus::NOT_FOUND);
    }

    private function output(Response $response): void
    {
        http_response_code($response->getStatus());
        echo $response->getContent();
    }
}