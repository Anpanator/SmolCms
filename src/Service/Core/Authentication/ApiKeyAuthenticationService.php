<?php

declare(strict_types=1);

namespace SmolCms\Service\Core\Authentication;

use SmolCms\Data\Constant\HttpHeader;
use SmolCms\Data\DTO\SessionUserData;
use SmolCms\Data\Request\Request;
use SmolCms\Service\DB\ApiKeyService;

readonly class ApiKeyAuthenticationService
{
    public function __construct(private ApiKeyService $apiKeyService)
    {
    }

    public function resolve(Request $request): ?SessionUserData
    {
        $authorization = self::getAuthorizationHeader($request->headers);
        if ($authorization === null
            || preg_match('/^Bearer\s+([a-f0-9]{64})$/iD', trim($authorization), $matches) !== 1) {
            return null;
        }

        return $this->apiKeyService->findUserDataByApiKey($matches[1]);
    }

    /** @param array<string, mixed> $headers */
    private static function getAuthorizationHeader(array $headers): ?string
    {
        foreach ($headers as $name => $value) {
            if (strcasecmp((string)$name, HttpHeader::AUTHORIZATION->value) === 0) {
                return is_string($value) ? $value : null;
            }
        }

        return null;
    }
}
