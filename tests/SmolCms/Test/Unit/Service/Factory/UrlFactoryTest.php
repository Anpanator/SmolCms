<?php

declare(strict_types=1);

namespace SmolCms\Test\Unit\Service\Factory;

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
            ->willReturn(new ValidationResult(true));
        $urlString = 'https://example.com:80/some/fancy/path?queryParam1=test';
        $url = $this->urlFactory->createUrlFromUrlString($urlString);
        self::assertSame('https', $url->protocol);
        self::assertSame('example.com', $url->host);
        self::assertSame(80, $url->port);
        self::assertSame('/some/fancy/path', $url->path);
        self::assertSame('queryParam1=test', $url->query);
    }

    public function testCreateUrlFromUrlString_failureValidationThrowsInvalidArgumentException()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->validator
            ->expects(self::atLeastOnce())
            ->method('validate')
            ->willReturn(new ValidationResult(false));
        $urlString = 'https://example.com:80/some/fancy/path?queryParam1=test';
        $this->urlFactory->createUrlFromUrlString($urlString);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->urlFactory = new UrlFactory($this->validator);
    }
}
