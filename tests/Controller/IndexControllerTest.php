<?php
declare(strict_types=1);

namespace SmolCms\Test\Controller;

use PHPUnit\Framework\Attributes\DataProvider;
use SmolCms\Data\Business\Url;
use SmolCms\Data\Constant\HttpContentType;
use SmolCms\Data\Constant\HttpHeader;
use SmolCms\Data\Constant\HttpMethod;
use SmolCms\Data\Constant\HttpStatus;
use SmolCms\Data\Constant\RouteEnum;
use SmolCms\Data\Request\Request;
use SmolCms\Data\Response\Response;
use SmolCms\TestUtils\FunctionalTestCase;

class IndexControllerTest extends FunctionalTestCase
{
    public static function dataProvider_shellRoutes(): array
    {
        return [
            'start page' => [RouteEnum::START_PAGE],
            'login' => [RouteEnum::LOGIN],
            'register' => [RouteEnum::REGISTER],
            'settings' => [RouteEnum::SETTINGS],
            'upload image' => [RouteEnum::UPLOAD_IMAGE],
            'image display' => [RouteEnum::IMAGE_DISPLAY],
            'image review' => [RouteEnum::IMAGE_REVIEW],
            'tags' => [RouteEnum::TAGS_PAGE],
            'tag queue' => [RouteEnum::TAG_QUEUE],
            'tagme queue' => [RouteEnum::TAGME_QUEUE],
            'user management' => [RouteEnum::USER_MANAGEMENT],
            'user profile' => [RouteEnum::USER_PROFILE],
            'audit log' => [RouteEnum::AUDIT_LOG],
        ];
    }

    #[DataProvider('dataProvider_shellRoutes')]
    public function testGetAction_ServesHtmlShell(RouteEnum $routeEnum): void
    {
        $request = new Request(
            url: new Url(protocol: 'https', host: 'localhost', path: $routeEnum->value),
            method: HttpMethod::GET,
        );

        $response = $this->simulateRequest($request);

        $this->assertHtmlShellResponse($response);
    }

    #[DataProvider('dataProvider_shellRoutes')]
    public function testGetAction_ServesHtmlShellForLoggedInUser(RouteEnum $routeEnum): void
    {
        $this->loginUser('shell-route-user', 'Shell Route User');
        $request = new Request(
            url: new Url(protocol: 'https', host: 'localhost', path: $routeEnum->value),
            method: HttpMethod::GET,
        );

        $response = $this->simulateRequest($request);

        $this->assertHtmlShellResponse($response);
    }

    private function assertHtmlShellResponse(Response $response): void
    {
        $this->assertSame(HttpStatus::OK, $response->getStatus());
        $this->assertSame(
            HttpContentType::HTML_UTF_8->value,
            $response->getHeaders()[HttpHeader::CONTENT_TYPE->value],
        );
        $content = (string)$response->getContent();
        $this->assertStringContainsString('<!DOCTYPE html>', $content);
        $this->assertStringContainsString('<title>SmolCms</title>', $content);
        $this->assertStringContainsString('<div id="app"></div>', $content);
        $this->assertStringContainsString('/public/css/main.css', $content);
        $this->assertStringContainsString('/public/js/main.js', $content);
        $this->assertStringContainsString('integrity="sha384-', $content);
        $this->assertStringContainsString('crossorigin="anonymous"', $content);
    }
}
