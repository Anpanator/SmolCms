<?php

declare(strict_types=1);

namespace SmolCms\Test\Service\Core;

use SmolCms\Data\Business\Url;
use SmolCms\Data\Constant\AccessLevel;
use SmolCms\Data\Constant\HttpHeader;
use SmolCms\Data\Constant\HttpMethod;
use SmolCms\Data\Request\Request;
use SmolCms\Data\Response\Response;
use SmolCms\Service\Core\Attribute\Authenticated;
use SmolCms\Service\Core\Attribute\CorsEnabled;
use SmolCms\Service\Core\CorsService;
use SmolCms\Service\Core\Session\SessionService;
use SmolCms\TestUtils\SimpleTestCase;

class CorsServiceTest extends SimpleTestCase
{
    private CorsService $corsService;

    /** @var array<string, mixed> */
    private array $originalCookie;

    protected function setUp(): void
    {
        parent::setUp();
        $this->corsService = new CorsService();
        $this->originalCookie = $_COOKIE;
        $_COOKIE = [];
    }

    protected function tearDown(): void
    {
        $_COOKIE = $this->originalCookie;
        parent::tearDown();
    }

    public function testIsCrossOriginDetectsFetchMetadataAndMismatchedOrigins(): void
    {
        self::assertFalse($this->corsService->isCrossOrigin($this->createRequest([
            HttpHeader::SEC_FETCH_SITE->value => 'same-origin',
            HttpHeader::ORIGIN->value => 'https://LOCALHOST',
        ])));
        self::assertFalse($this->corsService->isCrossOrigin($this->createRequest([])));
        self::assertTrue($this->corsService->isCrossOrigin($this->createRequest([
            HttpHeader::SEC_FETCH_SITE->value => 'same-site',
        ])));
        self::assertTrue($this->corsService->isCrossOrigin($this->createRequest([
            HttpHeader::ORIGIN->value => 'https://evil.example',
        ])));
        self::assertTrue($this->corsService->isCrossOrigin($this->createRequest([
            HttpHeader::ORIGIN->value => 'not-a-valid-origin',
        ])));
    }

    public function testAddResponseHeadersAddsPreflightHeadersOnlyForOptions(): void
    {
        $optionsResponse = new Response();
        $this->corsService->addResponseHeaders(
            $this->createRequest(
                [HttpHeader::ORIGIN->value => 'https://app.example'],
                HttpMethod::OPTIONS,
            ),
            $optionsResponse,
        );
        self::assertSame(
            '*',
            $optionsResponse->getHeaders()[HttpHeader::ACCESS_CONTROL_ALLOW_ORIGIN->value],
        );
        self::assertSame(
            'POST, OPTIONS',
            $optionsResponse->getHeaders()[HttpHeader::ACCESS_CONTROL_ALLOW_METHODS->value],
        );
        self::assertSame(
            'Authorization, Content-Type',
            $optionsResponse->getHeaders()[HttpHeader::ACCESS_CONTROL_ALLOW_HEADERS->value],
        );
        self::assertSame(
            '86400',
            $optionsResponse->getHeaders()[HttpHeader::ACCESS_CONTROL_MAX_AGE->value],
        );

        $postResponse = new Response();
        $this->corsService->addResponseHeaders(
            $this->createRequest([HttpHeader::ORIGIN->value => 'https://app.example']),
            $postResponse,
        );
        self::assertSame(
            '*',
            $postResponse->getHeaders()[HttpHeader::ACCESS_CONTROL_ALLOW_ORIGIN->value],
        );
        self::assertArrayNotHasKey(
            HttpHeader::ACCESS_CONTROL_ALLOW_METHODS->value,
            $postResponse->getHeaders(),
        );

        $sameOriginResponse = new Response();
        $this->corsService->addResponseHeaders($this->createRequest([]), $sameOriginResponse);
        self::assertSame([], $sameOriginResponse->getHeaders());
    }

    public function testHandlerAttributesAreReadFromTheControllerMethod(): void
    {
        $controller = new CorsServiceTest_Controller();

        self::assertTrue($this->corsService->isCorsEnabled($controller, 'upload'));
        self::assertTrue($this->corsService->isApiEnabled($controller, 'upload'));
        self::assertFalse($this->corsService->isCorsEnabled($controller, 'plain'));
        self::assertFalse($this->corsService->isApiEnabled($controller, 'plain'));
        self::assertFalse($this->corsService->isCorsEnabled($controller, 'missing'));
    }

    public function testCrossOriginApiKeyRequestRequiresCorsApiEnabledBearerAndNoSessionCookie(): void
    {
        $controller = new CorsServiceTest_Controller();
        $headers = [
            HttpHeader::AUTHORIZATION->value => 'Bearer ' . str_repeat('a', 64),
            HttpHeader::ORIGIN->value => 'https://app.example',
            HttpHeader::SEC_FETCH_SITE->value => 'cross-site',
        ];

        self::assertTrue($this->corsService->isCrossOriginApiKeyRequest(
            $this->createRequest($headers),
            $controller,
            'upload',
        ));
        self::assertFalse($this->corsService->isCrossOriginApiKeyRequest(
            $this->createRequest($headers + [HttpHeader::COOKIE->value => 'session=abc']),
            $controller,
            'upload',
        ));
        self::assertFalse($this->corsService->isCrossOriginApiKeyRequest(
            $this->createRequest([HttpHeader::ORIGIN->value => 'https://app.example']),
            $controller,
            'upload',
        ));
        self::assertFalse($this->corsService->isCrossOriginApiKeyRequest(
            $this->createRequest($headers),
            $controller,
            'plain',
        ));
    }

    public function testHasBearerAuthorizationRejectsOtherSchemesAndMalformedValues(): void
    {
        self::assertTrue($this->corsService->hasBearerAuthorization($this->createRequest([
            HttpHeader::AUTHORIZATION->value => '  bearer   abc  ',
        ])));
        self::assertFalse($this->corsService->hasBearerAuthorization($this->createRequest([
            HttpHeader::AUTHORIZATION->value => 'Basic abc',
        ])));
        self::assertFalse($this->corsService->hasBearerAuthorization($this->createRequest([
            HttpHeader::AUTHORIZATION->value => 'Bearer',
        ])));
        self::assertFalse($this->corsService->hasBearerAuthorization($this->createRequest([])));
    }

    public function testHasSessionCookieChecksTheHeaderAndCookieSuperglobal(): void
    {
        self::assertTrue($this->corsService->hasSessionCookie($this->createRequest([
            HttpHeader::COOKIE->value => 'theme=dark; ' . SessionService::SESSION_NAME . '=abc',
        ])));
        self::assertFalse($this->corsService->hasSessionCookie($this->createRequest([
            HttpHeader::COOKIE->value => 'theme=dark; other=abc',
        ])));

        $_COOKIE[SessionService::SESSION_NAME] = 'abc';
        self::assertTrue($this->corsService->hasSessionCookie($this->createRequest([])));
    }

    /** @param array<string, string> $headers */
    private function createRequest(
        array $headers,
        HttpMethod $method = HttpMethod::POST,
    ): Request
    {
        return new Request(
            url: new Url(protocol: 'https', host: 'localhost', path: '/upload-image'),
            method: $method,
            headers: $headers,
        );
    }
}

class CorsServiceTest_Controller
{
    #[CorsEnabled]
    #[Authenticated(AccessLevel::NOVICE, apiEnabled: true)]
    public function upload(): void
    {
    }

    #[Authenticated(AccessLevel::NOVICE)]
    public function plain(): void
    {
    }
}
