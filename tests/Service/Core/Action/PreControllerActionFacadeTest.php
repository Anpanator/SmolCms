<?php

declare(strict_types=1);

namespace SmolCms\Test\Service\Core\Action;

use PHPUnit\Framework\MockObject\MockObject;
use SmolCms\Data\Business\Url;
use SmolCms\Data\Constant\HttpMethod;
use SmolCms\Data\Constant\HttpStatus;
use SmolCms\Data\Request\Request;
use SmolCms\Data\Response\Response;
use SmolCms\Service\Core\Action\PreControllerAction;
use SmolCms\Service\Core\Action\PreControllerActionFacade;
use SmolCms\TestUtils\Attributes\Mock;
use SmolCms\TestUtils\SimpleTestCase;

class PreControllerActionFacadeTest extends SimpleTestCase
{
    #[Mock(PreControllerAction::class)]
    private PreControllerAction|MockObject $firstAction;

    #[Mock(PreControllerAction::class)]
    private PreControllerAction|MockObject $secondAction;

    private PreControllerActionFacade $facade;

    protected function setUp(): void
    {
        parent::setUp();
        $this->facade = new PreControllerActionFacade($this->firstAction, $this->secondAction);
    }

    public function testProcess_returnsEarlyResponseAndStopsRemainingActions(): void
    {
        $request = $this->createRequest();
        $controller = new PreControllerActionFacadeTestController();
        $earlyResponse = new Response(HttpStatus::UNAUTHORIZED);

        $this->firstAction
            ->expects($this->once())
            ->method('process')
            ->willReturn($earlyResponse)
            ->seal();
        $this->secondAction
            ->expects($this->never())
            ->method('process')
            ->seal();

        $result = $this->facade->process($request, $controller, 'handle', []);

        self::assertSame($earlyResponse, $result);
        self::assertSame(HttpStatus::UNAUTHORIZED, $result->getStatus());
        self::assertNull($controller->receivedValue);
    }

    public function testProcess_passesModifiedArgumentsToController(): void
    {
        $facade = new PreControllerActionFacade($this->firstAction);
        $request = $this->createRequest();
        $controller = new PreControllerActionFacadeTestController();

        $this->secondAction
            ->expects($this->never())
            ->method('process')
            ->seal();

        $this->firstAction
            ->expects($this->once())
            ->method('process')
            ->willReturnCallback(
                static function (
                    Request $request,
                    object  $controller,
                    string  $handler,
                    array   &$handlerArguments,
                ): ?Response {
                    $handlerArguments['value'] = 'modified';
                    return null;
                }
            )
            ->seal();

        $result = $facade->process($request, $controller, 'handle', ['value' => 'original']);

        self::assertSame('modified', $controller->receivedValue);
        self::assertSame('modified', $result->getContent());
    }

    private function createRequest(): Request
    {
        return new Request(
            url: new Url(protocol: 'https', host: 'example.com', path: '/test'),
            method: HttpMethod::POST,
        );
    }
}

class PreControllerActionFacadeTestController
{
    public ?string $receivedValue = null;

    public function handle(string $value): Response
    {
        $this->receivedValue = $value;
        return new Response(content: $value);
    }
}
