<?php

declare(strict_types=1);

namespace SmolCms\Test\Data\Business;

use PHPUnit\Framework\Attributes\DataProvider;
use SmolCms\Data\Business\Url;
use SmolCms\TestUtils\SimpleTestCase;

class UrlTest extends SimpleTestCase
{
    /**
     * @return array<string, array{Url, string}>
     */
    public static function urlStrings(): array
    {
        return [
            'path, port, and query' => [
                new Url(
                    protocol: 'https',
                    host: 'example.com',
                    port: 8443,
                    path: '/resource',
                    query: 'page=2',
                ),
                'https://example.com:8443/resource?page=2',
            ],
            'query uses a separator' => [
                new Url(protocol: 'https', host: 'example.com', query: 'page=2'),
                'https://example.com/?page=2',
            ],
            'path without port or query' => [
                new Url(protocol: 'https', host: 'example.com', path: '/resource'),
                'https://example.com/resource',
            ],
            'default path without port or query' => [
                new Url(protocol: 'https', host: 'example.com'),
                'https://example.com/',
            ],
            'empty query is omitted' => [
                new Url(protocol: 'https', host: 'example.com', path: '/resource', query: ''),
                'https://example.com/resource',
            ],
            'zero query is preserved' => [
                new Url(protocol: 'https', host: 'example.com', query: '0'),
                'https://example.com/?0',
            ],
        ];
    }

    /**
     * @param Url $url
     * @param string $expected
     */
    #[DataProvider('urlStrings')]
    public function testToString_formatsUrl(Url $url, string $expected): void
    {
        self::assertSame($expected, (string)$url);
    }
}
