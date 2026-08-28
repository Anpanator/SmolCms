<?php

declare(strict_types=1);

namespace SmolCms\Service\Factory {

    use SmolCms\Test\Service\Factory\RequestFactoryHeaderShim;

    function getallheaders(): array
    {
        return RequestFactoryHeaderShim::get();
    }
}

namespace SmolCms\Test\Service\Factory {

    use PHPUnit\Framework\MockObject\MockObject;
    use SmolCms\Data\Business\Url;
    use SmolCms\Data\Constant\HttpMethod;
    use SmolCms\Service\Factory\RequestFactory;
    use SmolCms\Service\Factory\UrlFactory;
    use SmolCms\TestUtils\Attributes\Mock;
    use SmolCms\TestUtils\SimpleTestCase;
    use ValueError;

    final class RequestFactoryHeaderShim
    {
        private static array $headers = [];

        public static function get(): array
        {
            return self::$headers;
        }

        public static function set(array $headers): void
        {
            self::$headers = $headers;
        }
    }

    class RequestFactoryTest extends SimpleTestCase
    {
        #[Mock(UrlFactory::class)]
        private UrlFactory|MockObject $urlFactory;

        private RequestFactory $requestFactory;
        private array $originalServer;
        private array $originalGet;
        private array $originalPost;
        private array $originalFiles;
        private array $originalHeaders;

        protected function setUp(): void
        {
            parent::setUp();
            $this->originalServer = $_SERVER;
            $this->originalGet = $_GET;
            $this->originalPost = $_POST;
            $this->originalFiles = $_FILES;
            $this->originalHeaders = RequestFactoryHeaderShim::get();
            $this->requestFactory = new RequestFactory($this->urlFactory);
        }

        protected function tearDown(): void
        {
            $_SERVER = $this->originalServer;
            $_GET = $this->originalGet;
            $_POST = $this->originalPost;
            $_FILES = $this->originalFiles;
            RequestFactoryHeaderShim::set($this->originalHeaders);
            parent::tearDown();
        }

        public function testBuildRequestFromGlobals_usesHttpWhenHttpsIsAbsent(): void
        {
            $this->setRequestGlobals();
            $url = $this->expectUrl('http://example.com/resource?from=uri');

            $request = $this->requestFactory->buildRequestFromGlobals();

            self::assertSame($url, $request->url);
        }

        public function testBuildRequestFromGlobals_usesHttpsWhenHttpsIsOn(): void
        {
            $this->setRequestGlobals(https: 'on');
            $url = $this->expectUrl('https://example.com/resource?from=uri');

            $request = $this->requestFactory->buildRequestFromGlobals();

            self::assertSame($url, $request->url);
        }

        public function testBuildRequestFromGlobals_usesHttpWhenHttpsIsOff(): void
        {
            $this->setRequestGlobals(https: 'off');
            $url = $this->expectUrl('http://example.com/resource?from=uri');

            $request = $this->requestFactory->buildRequestFromGlobals();

            self::assertSame($url, $request->url);
        }

        public function testBuildRequestFromGlobals_mapsQueryPostHeadersAndFiles(): void
        {
            $getParams = ['page' => '2', 'filter' => 'recent'];
            $postParams = ['title' => 'A title'];
            $headers = ['X-Correlation-ID' => 'request-123'];
            $files = [
                'attachment' => [
                    'name' => 'document.txt',
                    'type' => 'text/plain',
                    'tmp_name' => '/tmp/document.txt',
                    'error' => UPLOAD_ERR_OK,
                    'size' => 12,
                ],
            ];
            $this->setRequestGlobals(
                https: 'on',
                method: 'POST',
                getParams: $getParams,
                postParams: $postParams,
                headers: $headers,
                files: $files,
            );
            $url = $this->expectUrl('https://example.com/resource?from=uri');

            $request = $this->requestFactory->buildRequestFromGlobals();

            self::assertSame($url, $request->url);
            self::assertSame(HttpMethod::POST, $request->method);
            self::assertSame($headers, $request->headers);
            self::assertSame($postParams, $request->postParams);
            self::assertSame($getParams, $request->getParams);
            self::assertSame($files, $request->files);
        }

        public function testBuildRequestFromGlobals_rejectsUnsupportedHttpMethod(): void
        {
            $this->setRequestGlobals(method: 'PATCH');
            $this->expectUrl('http://example.com/resource?from=uri');
            $this->expectException(ValueError::class);

            $this->requestFactory->buildRequestFromGlobals();
        }

        private function setRequestGlobals(
            ?string $https = null,
            string  $method = 'GET',
            array   $getParams = [],
            array   $postParams = [],
            array   $headers = ['X-Test-Header' => 'test-value'],
            array   $files = [],
        ): void
        {
            $_SERVER = [
                'HTTP_HOST' => 'example.com',
                'REQUEST_URI' => '/resource?from=uri',
                'REQUEST_METHOD' => $method,
            ];
            if ($https !== null) {
                $_SERVER['HTTPS'] = $https;
            }
            $_GET = $getParams;
            $_POST = $postParams;
            $_FILES = $files;
            RequestFactoryHeaderShim::set($headers);
        }

        private function expectUrl(string $urlString): Url
        {
            $url = new Url(
                protocol: 'https',
                host: 'example.com',
                path: '/resource',
                query: 'from=uri',
            );
            $this->urlFactory
                ->expects(self::once())
                ->method('createUrlFromUrlString')
                ->with($urlString)
                ->willReturn($url)
                ->seal();
            return $url;
        }
    }
}
