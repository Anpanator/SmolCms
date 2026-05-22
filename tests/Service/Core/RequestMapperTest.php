<?php

declare(strict_types=1);

namespace SmolCms\Test\Service\Core;

use PHPUnit\Framework\MockObject\MockObject;
use RuntimeException;
use SmolCms\Data\Business\Url;
use SmolCms\Data\Constant\HttpMethod;
use SmolCms\Data\Request\Request;
use SmolCms\Data\Request\ValidatedRequest;
use SmolCms\Data\ValidationResult;
use SmolCms\Exception\BadRequestException;
use SmolCms\Service\Core\RequestMapper;
use SmolCms\Service\Validation\Validator;
use SmolCms\TestUtils\Attributes\Mock;
use SmolCms\TestUtils\SimpleTestCase;

class RequestMapperTest extends SimpleTestCase
{
    private RequestMapper $requestMapper;
    #[Mock(Validator::class)]
    private Validator|MockObject $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->requestMapper = new RequestMapper($this->validator);
    }

    public function testMapRequest_returnsRawRequestWhenHandlerTakesRequest(): void
    {
        $this->validator->expects(self::never())->method('validate');
        $request = $this->createRequest();
        $controller = new TestController();

        $result = $this->requestMapper->mapRequest($request, $controller, 'requestAction');

        self::assertSame($request, $result);
    }

    public function testMapRequest_mapsPostParamsIntoValidatedRequest(): void
    {
        $this->validator->method('validate')->willReturn(new ValidationResult(isValid: true));
        $request = $this->createRequest(postParams: ['name' => 'Alice', 'email' => 'alice@example.com']);
        $controller = new TestController();

        $result = $this->requestMapper->mapRequest($request, $controller, 'validatedAction');

        self::assertInstanceOf(SimpleTestRequest::class, $result);
        self::assertSame('Alice', $result->name);
        self::assertSame('alice@example.com', $result->email);
    }

    public function testMapRequest_postParamsTakePriorityOverGetParams(): void
    {
        $this->validator->method('validate')->willReturn(new ValidationResult(isValid: true));
        $request = $this->createRequest(
            postParams: ['name' => 'PostName'],
            getParams: ['name' => 'GetName'],
        );
        $controller = new TestController();

        $result = $this->requestMapper->mapRequest($request, $controller, 'validatedAction');

        self::assertInstanceOf(SimpleTestRequest::class, $result);
        self::assertSame('PostName', $result->name);
    }

    public function testMapRequest_missingParamsDefaultToNull(): void
    {
        $this->validator->method('validate')->willReturn(new ValidationResult(isValid: true));
        $request = $this->createRequest(postParams: ['name' => 'Bob']);
        $controller = new TestController();

        $result = $this->requestMapper->mapRequest($request, $controller, 'validatedAction');

        self::assertInstanceOf(SimpleTestRequest::class, $result);
        self::assertSame('Bob', $result->name);
        self::assertNull($result->email);
    }

    public function testMapRequest_rawRequestIsInjectedIntoMappedObject(): void
    {
        $this->validator->method('validate')->willReturn(new ValidationResult(isValid: true));
        $request = $this->createRequest();
        $controller = new TestController();

        $result = $this->requestMapper->mapRequest($request, $controller, 'validatedAction');

        self::assertInstanceOf(SimpleTestRequest::class, $result);
        self::assertSame($request, $result->rawRequest);
    }

    public function testMapRequest_throwsBadRequestExceptionOnTypeError(): void
    {
        $request = $this->createRequest(postParams: []);
        $controller = new TestController();

        $this->expectException(BadRequestException::class);
        $this->requestMapper->mapRequest($request, $controller, 'strictAction');
    }

    public function testMapRequest_throwsBadRequestExceptionWhenValidationFails(): void
    {
        $this->validator->method('validate')->willReturn(
            new ValidationResult(isValid: false, messages: ['field' => 'error'])
        );
        $request = $this->createRequest(postParams: ['name' => 'Alice']);
        $controller = new TestController();

        $this->expectException(BadRequestException::class);
        $this->requestMapper->mapRequest($request, $controller, 'validatedAction');
    }

    public function testMapRequest_throwsRuntimeExceptionWhenNoRequestParameterFound(): void
    {
        $request = $this->createRequest();
        $controller = new TestController();

        $this->expectException(RuntimeException::class);
        $this->requestMapper->mapRequest($request, $controller, 'noRequestAction');
    }

    public function testMapRequest_throwsRuntimeExceptionWhenValidatedRequestClassIsNotReadonly(): void
    {
        $request = $this->createRequest();
        $controller = new TestController();

        $this->expectException(RuntimeException::class);
        $this->requestMapper->mapRequest($request, $controller, 'nonReadonlyAction');
    }

    private function createRequest(?array $postParams = null, ?array $getParams = null): Request
    {
        return new Request(
            url: new Url(protocol: 'https', host: 'example.com', path: '/test'),
            method: HttpMethod::POST,
            postParams: $postParams,
            getParams: $getParams,
        );
    }
}

readonly class SimpleTestRequest extends ValidatedRequest
{
    public function __construct(
        Request        $rawRequest,
        public ?string $name = null,
        public ?string $email = null,
    )
    {
        parent::__construct($rawRequest);
    }
}

readonly class StrictTestRequest extends ValidatedRequest
{
    public function __construct(
        Request       $rawRequest,
        public string $required,
    )
    {
        parent::__construct($rawRequest);
    }
}

readonly class NonReadonlyTestRequest extends ValidatedRequest
{
    public function __construct(Request $rawRequest)
    {
        parent::__construct($rawRequest);
    }
}

class PlainObject
{
}

class TestController
{
    public function requestAction(Request $request): void
    {
    }

    public function validatedAction(SimpleTestRequest $request): void
    {
    }

    public function strictAction(StrictTestRequest $request): void
    {
    }

    public function nonReadonlyAction(NonReadonlyTestRequest $request): void
    {
    }

    public function noRequestAction(PlainObject $something): void
    {
    }
}
