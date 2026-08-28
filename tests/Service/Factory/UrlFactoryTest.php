<?php

declare(strict_types=1);

namespace SmolCms\Test\Service\Factory;

use InvalidArgumentException;
use PHPUnit\Framework\MockObject\MockObject;
use SmolCms\Data\ValidationResult;
use SmolCms\Service\Factory\UrlFactory;
use SmolCms\Service\Validation\Validator;
use SmolCms\TestUtils\Attributes\Mock;
use SmolCms\TestUtils\SimpleTestCase;

class UrlFactoryTest extends SimpleTestCase
{
    private UrlFactory $urlFactory;
    #[Mock(Validator::class)]
    private Validator|MockObject $validator;

    public function testCreateUrlFromUrlString_success()
    {
        $this->validator
            ->expects(self::atLeastOnce())
            ->method('validate')
            ->willReturn(new ValidationResult(true))
            ->seal();
        $urlString = 'https://example.com:80/some/fancy/path?queryParam1=test';
        $url = $this->urlFactory->createUrlFromUrlString($urlString);
        self::assertSame('https', $url->protocol);
        self::assertSame('example.com', $url->host);
        self::assertSame(80, $url->port);
        self::assertSame('/some/fancy/path', $url->path);
        self::assertSame('queryParam1=test', $url->query);
        self::assertSame($urlString, (string)$url);
    }

    public function testCreateUrlFromUrlString_failureValidationThrowsInvalidArgumentException()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->validator
            ->expects(self::atLeastOnce())
            ->method('validate')
            ->willReturn(new ValidationResult(false))
            ->seal();
        $urlString = 'https://example.com:80/some/fancy/path?queryParam1=test';
        $this->urlFactory->createUrlFromUrlString($urlString);
    }

    public function testCreateUrlFromUrlString_malformedUrlThrowsInvalidArgumentException(): void
    {
        $this->validator
            ->expects(self::never())
            ->method('validate')
            ->seal();
        $this->expectException(InvalidArgumentException::class);

        $this->urlFactory->createUrlFromUrlString('https://example.com:65536');
    }

    public function testCreateUrlFromUrlString_invalidProtocolThrowsInvalidArgumentException(): void
    {
        $this->validator
            ->expects(self::never())
            ->method('validate')
            ->seal();
        $this->expectException(InvalidArgumentException::class);

        (new UrlFactory(new Validator()))->createUrlFromUrlString('ftp://example.com/');
    }

    public function testCreateUrlFromUrlString_invalidHostThrowsInvalidArgumentException(): void
    {
        $this->validator
            ->expects(self::never())
            ->method('validate')
            ->seal();
        $this->expectException(InvalidArgumentException::class);

        (new UrlFactory(new Validator()))->createUrlFromUrlString('https://invalid host/');
    }

    public function testCreateUrlFromUrlString_invalidPortThrowsInvalidArgumentException(): void
    {
        $this->validator
            ->expects(self::never())
            ->method('validate')
            ->seal();
        $this->expectException(InvalidArgumentException::class);

        (new UrlFactory(new Validator()))->createUrlFromUrlString('https://example.com:0/');
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->urlFactory = new UrlFactory($this->validator);
    }
}
