<?php

declare(strict_types=1);

namespace SmolCms\Service\Core\Action;

use SmolCms\Data\Constant\HttpHeader;
use SmolCms\Data\Constant\HttpMethod;
use SmolCms\Data\Constant\HttpStatus;
use SmolCms\Data\Request\Request;
use SmolCms\Data\Response\Response;

final readonly class SameOriginPreControllerAction implements PreControllerAction
{
    public function process(Request $request, object $controller, string $handler, array &$handlerArguments): ?Response
    {
        if (!in_array($request->method, [HttpMethod::POST, HttpMethod::PUT, HttpMethod::DELETE], true)) {
            return null;
        }

        $fetchSite = self::getHeader($request, HttpHeader::SEC_FETCH_SITE->value);
        if ($fetchSite !== null && strcasecmp(trim($fetchSite), 'same-origin') !== 0) {
            return new Response(HttpStatus::FORBIDDEN);
        }

        $origin = self::getHeader($request, HttpHeader::ORIGIN->value);
        if ($origin === null) {
            return null;
        }

        $originHost = parse_url(trim($origin), PHP_URL_HOST);
        if (!is_string($originHost) || strcasecmp($originHost, $request->url->host) !== 0) {
            return new Response(HttpStatus::FORBIDDEN);
        }

        return null;
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
