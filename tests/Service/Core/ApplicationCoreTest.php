<?php

declare(strict_types=1);

namespace SmolCms\Test\Service\Core;

use SmolCms\Data\Business\Url;
use SmolCms\Data\Constant\HttpMethod;
use SmolCms\Data\Constant\HttpStatus;
use SmolCms\Data\Request\Request;
use SmolCms\TestUtils\FunctionalTestCase;

class ApplicationCoreTest extends FunctionalTestCase
{
    public function testSimulateRequest_returnsNotFoundForUnknownPath(): void
    {
        $response = $this->simulateRequest($this->createRequest('/does-not-exist', HttpMethod::GET));

        self::assertSame(HttpStatus::NOT_FOUND, $response->getStatus());
    }

    public function testSimulateRequest_returnsNotFoundForUnsupportedMethod(): void
    {
        $response = $this->simulateRequest($this->createRequest('/login', HttpMethod::PUT));

        self::assertSame(HttpStatus::NOT_FOUND, $response->getStatus());
    }

    private function createRequest(string $path, HttpMethod $method): Request
    {
        return new Request(
            url: new Url(protocol: 'https', host: 'localhost', path: $path),
            method: $method,
        );
    }
}
