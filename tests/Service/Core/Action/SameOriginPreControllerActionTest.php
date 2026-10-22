<?php

declare(strict_types=1);

namespace SmolCms\Test\Service\Core\Action;

use SmolCms\Data\Business\Url;
use SmolCms\Data\Constant\AccessLevel;
use SmolCms\Data\Constant\HttpHeader;
use SmolCms\Data\Constant\HttpMethod;
use SmolCms\Data\Constant\HttpStatus;
use SmolCms\Data\Request\Request;
use SmolCms\Data\Response\Response;
use SmolCms\Service\Core\Action\SameOriginPreControllerAction;
use SmolCms\Service\Core\Attribute\Authenticated;
use SmolCms\Service\Core\Attribute\CorsEnabled;
use SmolCms\Service\Core\CorsService;
use SmolCms\TestUtils\SimpleTestCase;

class SameOriginPreControllerActionTest extends SimpleTestCase
{
    private SameOriginPreControllerAction $action;

    protected function setUp(): void
    {
        parent::setUp();
        $this->action = new SameOriginPreControllerAction(new CorsService());
    }

    public function testProcessRejectsFetchMetadataFromOtherOrigins(): void
    {
        $response = $this->processRequest([
            HttpHeader::SEC_FETCH_SITE->value => 'same-site',
            HttpHeader::ORIGIN->value => 'https://localhost',
        ]);

        self::assertNotNull($response);
        self::assertSame(HttpStatus::FORBIDDEN, $response->getStatus());
    }

    public function testProcessRejectsMismatchingOriginWhenFetchMetadataIsAbsent(): void
    {
        $response = $this->processRequest([HttpHeader::ORIGIN->value => 'https://evil.example']);

        self::assertNotNull($response);
        self::assertSame(HttpStatus::FORBIDDEN, $response->getStatus());
    }

    public function testProcessAllowsSameOriginAndRequestsWithoutBrowserSignals(): void
    {
        self::assertNull($this->processRequest([
            HttpHeader::SEC_FETCH_SITE->value => 'same-origin',
            HttpHeader::ORIGIN->value => 'https://LOCALHOST',
        ]));
        self::assertNull($this->processRequest([]));
    }

    public function testProcessDoesNotApplyToSafeMethods(): void
    {
        $request = new Request(
            url: new Url(protocol: 'https', host: 'localhost', path: '/login'),
            method: HttpMethod::GET,
            headers: [HttpHeader::SEC_FETCH_SITE->value => 'cross-site'],
        );
        $handlerArguments = [];

        self::assertNull($this->action->process($request, new \stdClass(), 'handler', $handlerArguments));
    }

    public function testProcessAllowsCrossOriginApiKeyRequestForCorsEnabledHandler(): void
    {
        self::assertNull($this->processRequest(
            $this->crossOriginApiKeyHeaders(),
            new SameOriginPreControllerActionTest_CorsController(),
            'upload',
        ));
    }

    public function testProcessRejectsCrossOriginRequestWithoutApiKey(): void
    {
        $response = $this->processRequest(
            [
                HttpHeader::ORIGIN->value => 'https://evil.example',
                HttpHeader::SEC_FETCH_SITE->value => 'cross-site',
            ],
            new SameOriginPreControllerActionTest_CorsController(),
            'upload',
        );

        self::assertNotNull($response);
        self::assertSame(HttpStatus::FORBIDDEN, $response->getStatus());
    }

    public function testProcessRejectsCrossOriginApiKeyRequestWithSessionCookie(): void
    {
        $headers = $this->crossOriginApiKeyHeaders();
        $headers[HttpHeader::COOKIE->value] = 'session=abc123';

        $response = $this->processRequest(
            $headers,
            new SameOriginPreControllerActionTest_CorsController(),
            'upload',
        );

        self::assertNotNull($response);
        self::assertSame(HttpStatus::FORBIDDEN, $response->getStatus());
    }

    public function testProcessRejectsCrossOriginApiKeyRequestForHandlerWithoutCors(): void
    {
        $response = $this->processRequest(
            $this->crossOriginApiKeyHeaders(),
            new SameOriginPreControllerActionTest_ApiOnlyController(),
            'upload',
        );

        self::assertNotNull($response);
        self::assertSame(HttpStatus::FORBIDDEN, $response->getStatus());
    }

    /** @return array<string, string> */
    private function crossOriginApiKeyHeaders(): array
    {
        return [
            HttpHeader::AUTHORIZATION->value => 'Bearer ' . str_repeat('a', 64),
            HttpHeader::ORIGIN->value => 'https://evil.example',
            HttpHeader::SEC_FETCH_SITE->value => 'cross-site',
        ];
    }

    private function processRequest(
        array $headers,
        ?object $controller = null,
        string $handler = 'handler',
    ): ?Response
    {
        $request = new Request(
            url: new Url(protocol: 'https', host: 'localhost', path: '/logout'),
            method: HttpMethod::POST,
            headers: $headers,
        );
        $handlerArguments = [];
        return $this->action->process($request, $controller ?? new \stdClass(), $handler, $handlerArguments);
    }
}

class SameOriginPreControllerActionTest_CorsController
{
    #[CorsEnabled]
    #[Authenticated(AccessLevel::NOVICE, apiEnabled: true)]
    public function upload(): void
    {
    }
}

class SameOriginPreControllerActionTest_ApiOnlyController
{
    #[Authenticated(AccessLevel::NOVICE, apiEnabled: true)]
    public function upload(): void
    {
    }
}
