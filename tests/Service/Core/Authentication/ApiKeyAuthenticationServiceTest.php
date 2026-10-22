<?php

declare(strict_types=1);

namespace SmolCms\Test\Service\Core\Authentication;

use PHPUnit\Framework\MockObject\MockObject;
use SmolCms\Data\Business\Url;
use SmolCms\Data\Constant\HttpMethod;
use SmolCms\Data\DTO\SessionUserData;
use SmolCms\Data\Request\Request;
use SmolCms\Service\Core\Authentication\ApiKeyAuthenticationService;
use SmolCms\Service\DB\ApiKeyService;
use SmolCms\TestUtils\Attributes\Mock;
use SmolCms\TestUtils\SimpleTestCase;

class ApiKeyAuthenticationServiceTest extends SimpleTestCase
{
    private ApiKeyAuthenticationService $authenticationService;

    #[Mock(ApiKeyService::class)]
    private ApiKeyService|MockObject $apiKeyService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->authenticationService = new ApiKeyAuthenticationService($this->apiKeyService);
    }

    public function testResolveExtractsBearerKeyAndReturnsTheOwner(): void
    {
        $apiKey = str_repeat('a', 64);
        $userData = new SessionUserData(7, 'Key Owner');
        $request = $this->createRequest(['authorization' => 'bEaReR ' . $apiKey]);
        $this->apiKeyService
            ->expects(self::once())
            ->method('findUserDataByApiKey')
            ->with($apiKey)
            ->willReturn($userData)
            ->seal();

        self::assertSame($userData, $this->authenticationService->resolve($request));
    }

    public function testResolveIgnoresMissingOrMalformedBearerHeaders(): void
    {
        $this->apiKeyService
            ->expects(self::never())
            ->method('findUserDataByApiKey')
            ->seal();

        foreach ([
            [],
            ['Authorization' => 'Basic ' . str_repeat('a', 64)],
            ['Authorization' => 'Bearer short'],
            ['Authorization' => 'Bearer ' . str_repeat('g', 64)],
            ['Authorization' => ['Bearer ' . str_repeat('a', 64)]],
        ] as $headers) {
            self::assertNull($this->authenticationService->resolve($this->createRequest($headers)));
        }
    }

    private function createRequest(array $headers): Request
    {
        return new Request(
            url: new Url(protocol: 'https', host: 'localhost', path: '/upload-image'),
            method: HttpMethod::POST,
            headers: $headers,
        );
    }
}
