<?php

declare(strict_types=1);

namespace SmolCms\Test\Data\Response;

use SmolCms\Data\Constant\HttpStatus;
use SmolCms\Data\Response\JsonResponse;
use SmolCms\Exception\InvalidStateException;
use SmolCms\TestUtils\SimpleTestCase;

class JsonResponseTest extends SimpleTestCase
{
    public function testConstructor_EncodesDataAndSetsContentType(): void
    {
        $data = ['user' => ['id' => 1, 'displayName' => 'Test User', 'accessLevel' => 100]];

        $response = new JsonResponse($data);

        self::assertSame(HttpStatus::OK, $response->getStatus());
        self::assertSame('application/json', $response->getHeaders()['Content-Type']);
        self::assertSame('{"user":{"id":1,"displayName":"Test User","accessLevel":100}}', $response->getContent());
    }

    public function testConstructor_SupportsCustomStatus(): void
    {
        $response = new JsonResponse(['created' => true], HttpStatus::CREATED);

        self::assertSame(HttpStatus::CREATED, $response->getStatus());
        self::assertSame('{"created":true}', $response->getContent());
    }

    public function testConstructor_NullDataProducesNoContent(): void
    {
        $response = new JsonResponse(null, HttpStatus::NO_CONTENT);

        self::assertSame(HttpStatus::NO_CONTENT, $response->getStatus());
        self::assertNull($response->getContent());
    }

    public function testConstructor_EmptyArrayIsEncodedAsEmptyJsonArray(): void
    {
        $response = new JsonResponse([]);

        self::assertSame('[]', $response->getContent());
        self::assertSame('application/json', $response->getHeaders()['Content-Type']);
    }

    public function testConstructor_ThrowsInvalidStateExceptionOnUnencodableData(): void
    {
        $unencodable = "\xB1\x31"; // Invalid UTF-8 sequence

        $this->expectException(InvalidStateException::class);
        new JsonResponse(['data' => $unencodable]);
    }
}
