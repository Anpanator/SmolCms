<?php

declare(strict_types=1);

namespace SmolCms\Service\Factory;


use SmolCms\Data\Constant\HttpMethod;
use SmolCms\Data\Request\Request;

class RequestFactory
{


    /**
     * RequestFactory constructor.
     */
    public function __construct(
        private UrlFactory $urlFactory
    )
    {
    }

    public function buildRequestFromGlobals(): Request
    {
        $isHttps = $_SERVER['HTTPS'] ?? '' === 'on';
        $urlString = $this->getRequestUrl($_SERVER['HTTP_HOST'] ?? '', $_SERVER['REQUEST_URI'] ?? '', $isHttps);
        $url = $this->urlFactory->createUrlFromUrlString($urlString);
        return new Request(
            url: $url,
            method: HttpMethod::from($_SERVER['REQUEST_METHOD']),
            headers: getallheaders() ?: null,
            rawBody: file_get_contents('php://input') ?: null,
            postParams: $_POST ?: null,
            getParams: $_GET ?: null
        );
    }


    /**
     * @param string $host
     * @param string $uri
     * @param bool $isHttps
     * @return string
     */
    private function getRequestUrl(string $host, string $uri, bool $isHttps): string
    {
        return ($isHttps ? "https" : "http") . "://$host$uri";
    }
}