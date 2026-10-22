<?php

declare(strict_types=1);

namespace SmolCms\Service\Core;

use ReflectionException;
use ReflectionMethod;
use SmolCms\Data\Constant\HttpHeader;
use SmolCms\Data\Constant\HttpMethod;
use SmolCms\Data\Request\Request;
use SmolCms\Data\Response\Response;
use SmolCms\Service\Core\Attribute\Authenticated;
use SmolCms\Service\Core\Attribute\CorsEnabled;
use SmolCms\Service\Core\Session\SessionService;

final readonly class CorsService
{
    private const string ALLOWED_METHODS = 'POST, OPTIONS';
    private const string ALLOWED_HEADERS = 'Authorization, Content-Type';
    private const string MAX_AGE_SECONDS = '86400';
    private const string BEARER_PATTERN = '/^Bearer\s+\S+$/i';

    public function isCrossOrigin(Request $request): bool
    {
        $fetchSite = self::getHeader($request, HttpHeader::SEC_FETCH_SITE->value);
        if ($fetchSite !== null && strcasecmp(trim($fetchSite), 'same-origin') !== 0) {
            return true;
        }

        $origin = self::getHeader($request, HttpHeader::ORIGIN->value);
        if ($origin === null) {
            return false;
        }

        $originHost = parse_url(trim($origin), PHP_URL_HOST);
        return !is_string($originHost) || strcasecmp($originHost, $request->url->host) !== 0;
    }

    public function isCorsEnabled(object $controller, string $handler): bool
    {
        return $this->getHandlerAttribute($controller, $handler, CorsEnabled::class) !== null;
    }

    public function isApiEnabled(object $controller, string $handler): bool
    {
        $attribute = $this->getHandlerAttribute($controller, $handler, Authenticated::class);
        return $attribute instanceof Authenticated && $attribute->apiEnabled;
    }

    public function isCrossOriginApiKeyRequest(Request $request, object $controller, string $handler): bool
    {
        return $this->isCrossOrigin($request)
            && $this->isCorsEnabled($controller, $handler)
            && $this->isApiEnabled($controller, $handler)
            && $this->hasBearerAuthorization($request)
            && !$this->hasSessionCookie($request);
    }

    public function addResponseHeaders(Request $request, Response $response): void
    {
        if (self::getHeader($request, HttpHeader::ORIGIN->value) === null) {
            return;
        }

        $response->addHeader(HttpHeader::ACCESS_CONTROL_ALLOW_ORIGIN->value, '*');
        if ($request->method !== HttpMethod::OPTIONS) {
            return;
        }

        $response->addHeader(HttpHeader::ACCESS_CONTROL_ALLOW_METHODS->value, self::ALLOWED_METHODS);
        $response->addHeader(HttpHeader::ACCESS_CONTROL_ALLOW_HEADERS->value, self::ALLOWED_HEADERS);
        $response->addHeader(HttpHeader::ACCESS_CONTROL_MAX_AGE->value, self::MAX_AGE_SECONDS);
    }

    public function hasBearerAuthorization(Request $request): bool
    {
        $authorization = self::getHeader($request, HttpHeader::AUTHORIZATION->value);
        return $authorization !== null
            && preg_match(self::BEARER_PATTERN, trim($authorization)) === 1;
    }

    public function hasSessionCookie(Request $request): bool
    {
        $cookieHeader = self::getHeader($request, HttpHeader::COOKIE->value);
        if ($cookieHeader !== null) {
            foreach (explode(';', $cookieHeader) as $cookie) {
                $name = trim(explode('=', $cookie, 2)[0]);
                if ($name === SessionService::SESSION_NAME) {
                    return true;
                }
            }
        }

        return isset($_COOKIE[SessionService::SESSION_NAME]);
    }

    /**
     * @template T of object
     * @param class-string<T> $attributeClass
     * @return T|null
     */
    private function getHandlerAttribute(object $controller, string $handler, string $attributeClass): ?object
    {
        try {
            $method = new ReflectionMethod($controller, $handler);
        } catch (ReflectionException) {
            return null;
        }

        $attributes = $method->getAttributes($attributeClass);
        return $attributes === [] ? null : $attributes[0]->newInstance();
    }

    private static function getHeader(Request $request, string $headerName): ?string
    {
        foreach ($request->headers as $name => $value) {
            if (is_string($name) && strcasecmp($name, $headerName) === 0) {
                return is_string($value) ? $value : '';
            }
        }
        return null;
    }
}
