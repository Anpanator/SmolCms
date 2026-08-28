<?php

declare(strict_types=1);

namespace SmolCms\Test\Service\Core\Action;

use PHPUnit\Framework\MockObject\MockObject;
use SmolCms\Data\Business\Url;
use SmolCms\Data\Constant\HttpMethod;
use SmolCms\Data\Request\Request;
use SmolCms\Data\Request\ValidatedRequest;
use SmolCms\Data\Response\Response;
use SmolCms\Data\ValidationResult;
use SmolCms\Exception\BadRequestException;
use SmolCms\Service\Core\Action\ValidationPreControllerAction;
use SmolCms\Service\Validation\Validator;
use SmolCms\TestUtils\Attributes\Mock;
use SmolCms\TestUtils\SimpleTestCase;

class ValidationPreControllerActionTest extends SimpleTestCase
{
    #[Mock(Validator::class)]
    private Validator|MockObject $validator;

    private ValidationPreControllerAction $action;

    protected function setUp(): void
    {
        parent::setUp();
        $this->action = new ValidationPreControllerAction($this->validator);
    }

    public function testProcess_bypassesValidationForPlainRequest(): void
    {
        $request = $this->createRequest();
        $controller = new ValidationPreControllerActionTestController();
        $handlerArguments = ['request' => $request];

        $this->validator
            ->expects($this->never())
            ->method('validate')
            ->seal();

        $result = $this->action->process($request, $controller, 'requestAction', $handlerArguments);

        self::assertNull($result);
    }

    public function testProcess_throwsBadRequestForInvalidValidatedRequest(): void
    {
        $request = $this->createRequest();
        $mappedRequest = new ValidationPreControllerActionTestRequest($request);
        $controller = new ValidationPreControllerActionTestController();
        $handlerArguments = ['request' => $mappedRequest];

        $this->validator
            ->expects($this->once())
            ->method('validate')
            ->with($mappedRequest)
            ->willReturn(new ValidationResult(false, ['name' => 'Invalid name']))
            ->seal();

        $this->expectException(BadRequestException::class);
        $this->expectExceptionMessageIsOrContains(
            'Validation failed for request in '
            . ValidationPreControllerActionTestController::class
            . '::validatedAction'
        );

        $this->action->process($request, $controller, 'validatedAction', $handlerArguments);
    }

    public function testProcess_allowsValidMappedRequest(): void
    {
        $request = $this->createRequest();
        $mappedRequest = new ValidationPreControllerActionTestRequest($request);
        $controller = new ValidationPreControllerActionTestController();
        $handlerArguments = ['request' => $mappedRequest];

        $this->validator
            ->expects($this->once())
            ->method('validate')
            ->with($mappedRequest)
            ->willReturn(new ValidationResult(true))
            ->seal();

        $result = $this->action->process($request, $controller, 'validatedAction', $handlerArguments);

        self::assertNull($result);
        self::assertSame($mappedRequest, $handlerArguments['request']);
    }

    private function createRequest(): Request
    {
        return new Request(
            url: new Url(protocol: 'https', host: 'example.com', path: '/test'),
            method: HttpMethod::POST,
        );
    }
}

readonly class ValidationPreControllerActionTestRequest extends ValidatedRequest
{
}

class ValidationPreControllerActionTestController
{
    public function requestAction(Request $request): Response
    {
        return new Response();
    }

    public function validatedAction(ValidationPreControllerActionTestRequest $request): Response
    {
        return new Response();
    }
}
