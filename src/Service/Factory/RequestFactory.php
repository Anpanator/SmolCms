<?php

declare(strict_types=1);

namespace SmolCms\Service\Factory;


use Closure;
use JsonException;
use SmolCms\Data\Constant\HttpContentType;
use SmolCms\Data\Constant\HttpHeader;
use SmolCms\Data\Constant\HttpMethod;
use SmolCms\Data\Request\Request;

class RequestFactory
{
    private Closure $bodyReader;


    /**
     * RequestFactory constructor.
     */
    public function __construct(
        private UrlFactory $urlFactory
    )
    {
        $this->bodyReader = static fn(string $stream): string|false => file_get_contents($stream);
    }

    /** Injects a body reader, primarily for request-body parsing tests. */
    public function setBodyReader(Closure $bodyReader): void
    {
        $this->bodyReader = $bodyReader;
    }

    public function buildRequestFromGlobals(): Request
    {
        $isHttps = ($_SERVER['HTTPS'] ?? '') === 'on';
        $urlString = $this->getRequestUrl($_SERVER['HTTP_HOST'] ?? '', $_SERVER['REQUEST_URI'] ?? '', $isHttps);
        $url = $this->urlFactory->createUrlFromUrlString($urlString);
        $headers = getallheaders() ?: null;
        return new Request(
            url: $url,
            method: HttpMethod::from($_SERVER['REQUEST_METHOD']),
            headers: $headers,
            postParams: $this->getPostParams($headers),
            getParams: $_GET ?: null,
            files: $_FILES ?: null,
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

    /**
     * Returns the JSON-decoded request body for JSON requests, otherwise falls back to the $_POST superglobal.
     * Unusable JSON bodies (invalid JSON, non-array results) yield null, so downstream validation produces 400.
     */
    private function getPostParams(?array $headers): ?array
    {
        if (!$this->isJsonContentType($headers)) {
            return $_POST ?: null;
        }
        $requestBody = ($this->bodyReader)('php://input');
        if ($requestBody === false) {
            return null;
        }
        try {
            $decoded = json_decode($requestBody, associative: true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }
        return is_array($decoded) ? $decoded : null;
    }

    private function isJsonContentType(?array $headers): bool
    {
        $contentType = '';
        foreach ($headers ?? [] as $name => $value) {
            if (strcasecmp($name, HttpHeader::CONTENT_TYPE->value) === 0) {
                $contentType = $value;
                break;
            }
        }
        return str_contains($contentType, HttpContentType::JSON->value);
    }
}
