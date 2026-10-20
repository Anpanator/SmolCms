<?php

declare(strict_types=1);

namespace SmolCms\Test\Service\Core\Action;

use SmolCms\Data\Business\Url;
use SmolCms\Data\Constant\HttpHeader;
use SmolCms\Data\Constant\HttpMethod;
use SmolCms\Data\Constant\HttpStatus;
use SmolCms\Data\Request\Request;
use SmolCms\Service\Core\Action\SameOriginPreControllerAction;
use SmolCms\TestUtils\SimpleTestCase;

class SameOriginPreControllerActionTest extends SimpleTestCase
{
    private SameOriginPreControllerAction $action;

    protected function setUp(): void
    {
        parent::setUp();
        $this->action = new SameOriginPreControllerAction();
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

    private function processRequest(array $headers): ?\SmolCms\Data\Response\Response
    {
        $request = new Request(
            url: new Url(protocol: 'https', host: 'localhost', path: '/logout'),
            method: HttpMethod::POST,
            headers: $headers,
        );
        $handlerArguments = [];
        return $this->action->process($request, new \stdClass(), 'handler', $handlerArguments);
    }
}
