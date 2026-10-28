<?php

declare(strict_types=1);

namespace SmolCms\Test\Service\Validation;

use SmolCms\Data\Business\Url;
use SmolCms\Data\Constant\HttpMethod;
use SmolCms\Data\Request\ImageTagRemoveRequest;
use SmolCms\Data\Request\ImageTagsRequest;
use SmolCms\Data\Request\Request;
use SmolCms\Data\Request\UserManagementUpdateRequest;
use SmolCms\Data\Request\ValidatedRequest;
use SmolCms\Exception\BadRequestException;
use SmolCms\Service\Core\Action\ValidationPreControllerAction;
use SmolCms\Service\Core\RequestMapper;
use SmolCms\Service\Validation\Validator;
use SmolCms\TestUtils\SimpleTestCase;

class RequestBoundaryValidationTest extends SimpleTestCase
{
    private RequestMapper $requestMapper;
    private ValidationPreControllerAction $validationAction;
    private RequestBoundaryController $controller;

    protected function setUp(): void
    {
        parent::setUp();
        $this->requestMapper = new RequestMapper();
        $this->validationAction = new ValidationPreControllerAction(new Validator());
        $this->controller = new RequestBoundaryController();
    }

    public function testImageTagRemoveRequestAcceptsOneHundredCharacterTag(): void
    {
        $tag = str_repeat('x', 100);
        $mapped = $this->mapAndValidate(
            $this->createRequest(['imageId' => 7, 'tag' => $tag]),
            'removeAction',
        );

        self::assertInstanceOf(ImageTagRemoveRequest::class, $mapped);
        self::assertSame($tag, $mapped->tag);
    }

    public function testImageTagRemoveRequestRejectsOneHundredAndOneCharacterTag(): void
    {
        $this->expectException(BadRequestException::class);
        $this->mapAndValidate(
            $this->createRequest(['imageId' => 7, 'tag' => str_repeat('x', 101)]),
            'removeAction',
        );
    }

    public function testImageTagRequestsRejectMissingImageId(): void
    {
        $this->expectException(BadRequestException::class);
        $this->mapAndValidate($this->createRequest(['tag' => 'valid-tag']), 'removeAction');
    }

    public function testImageTagsRequestRejectsMissingImageId(): void
    {
        $this->expectException(BadRequestException::class);
        $this->mapAndValidate($this->createRequest(['tags' => 'valid-tag']), 'addAction');
    }

    public function testUserManagementUpdateRequestAcceptsSixtyNineCharacterDisplayName(): void
    {
        $displayName = str_repeat('D', 69);
        $mapped = $this->mapAndValidate(
            $this->createRequest(['displayName' => $displayName, 'accessLevel' => 100], ['id' => '1']),
            'updateAction',
        );

        self::assertInstanceOf(UserManagementUpdateRequest::class, $mapped);
        self::assertSame($displayName, $mapped->displayName);
    }

    public function testUserManagementUpdateRequestRejectsSeventyCharacterDisplayName(): void
    {
        $this->expectException(BadRequestException::class);
        $this->mapAndValidate(
            $this->createRequest(['displayName' => str_repeat('D', 70), 'accessLevel' => 100], ['id' => '1']),
            'updateAction',
        );
    }

    private function mapAndValidate(Request $request, string $handler): ValidatedRequest
    {
        $mappedRequest = $this->requestMapper->mapRequest($request, $this->controller, $handler);
        self::assertInstanceOf(ValidatedRequest::class, $mappedRequest);
        $handlerArguments = ['request' => $mappedRequest];
        $this->validationAction->process($request, $this->controller, $handler, $handlerArguments);
        return $mappedRequest;
    }

    /** @param array<string, mixed> $postParams */
    private function createRequest(array $postParams, ?array $getParams = null): Request
    {
        return new Request(
            url: new Url(protocol: 'https', host: 'example.test', path: '/api/test'),
            method: HttpMethod::POST,
            postParams: $postParams,
            getParams: $getParams,
        );
    }
}

final class RequestBoundaryController
{
    public function removeAction(ImageTagRemoveRequest $request): void
    {
    }

    public function addAction(ImageTagsRequest $request): void
    {
    }

    public function updateAction(UserManagementUpdateRequest $request): void
    {
    }
}
