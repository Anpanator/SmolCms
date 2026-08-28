<?php
declare(strict_types=1);

namespace SmolCms\Test\Controller;

use SmolCms\Data\Business\Url;
use SmolCms\Data\Constant\HttpMethod;
use SmolCms\Data\Constant\HttpStatus;
use SmolCms\Data\Request\Request;
use SmolCms\Service\Core\Session\SessionService;
use SmolCms\Service\DB\UserService;
use SmolCms\TestUtils\Attributes\Autowire;
use SmolCms\TestUtils\FunctionalTestCase;
use Throwable;

class UserSettingControllerTest extends FunctionalTestCase
{
    #[Autowire]
    private UserService $userService;

    #[Autowire]
    private SessionService $sessionService;

    private Url $settingsUrl;

    protected function setUp(): void
    {
        parent::setUp();
        $this->settingsUrl = new Url(protocol: 'https', host: 'localhost', path: '/settings');
    }

    public function testGetAction_ShowsFormWhenLoggedIn(): void
    {
        $this->loginUser('form-test-user', 'Form User');

        $request = new Request(url: $this->settingsUrl, method: HttpMethod::GET);
        try {
            $response = $this->simulateRequest($request);
        } catch (Throwable $e) {
            $this->fail('Exception: ' . $e::class . ': ' . $e->getMessage() . "\n" . $e->getTraceAsString());
        }

        $this->assertEquals(HttpStatus::OK, $response->getStatus());
        $this->assertStringContainsString('User settings', $response->getContent());
    }

    public function testGetAction_ShowsEmptyPageWhenNotLoggedIn(): void
    {
        $request = new Request(url: $this->settingsUrl, method: HttpMethod::GET);
        try {
            $response = $this->simulateRequest($request);
        } catch (Throwable $e) {
            $this->fail('Exception: ' . $e::class . ': ' . $e->getMessage() . "\n" . $e->getTraceAsString());
        }

        $this->assertEquals(HttpStatus::OK, $response->getStatus());
    }

    public function testPostAction_SuccessfulUpdate(): void
    {
        $this->loginUser('update-test-user', 'Old Name');

        $request = new Request(
            url: $this->settingsUrl,
            method: HttpMethod::POST,
            postParams: ['displayName' => 'Updated Name']
        );
        $response = $this->simulateRequest($request);

        $this->assertEquals(HttpStatus::SEE_OTHER, $response->getStatus());
        $this->assertArrayHasKey('Location', $response->getHeaders());
        $this->assertSame('/settings', $response->getHeaders()['Location']);

        $userData = $this->sessionService->getUserData();
        $this->assertNotNull($userData);
        $this->assertSame('Updated Name', $userData->displayName);

        $savedUser = $this->userService->findOneById($userData->id);
        $this->assertNotNull($savedUser);
        $this->assertSame('Updated Name', $savedUser->getDisplayName());
    }

    public function testPostAction_RequiresAuthentication(): void
    {
        $request = new Request(
            url: $this->settingsUrl,
            method: HttpMethod::POST,
            postParams: ['displayName' => 'Should Not Work']
        );
        $response = $this->simulateRequest($request);

        $this->assertEquals(HttpStatus::UNAUTHORIZED, $response->getStatus());
        $this->assertNull($this->sessionService->getUserData());
    }

    public function testPostAction_UnauthenticatedMalformedRequestIsRejectedBeforeValidation(): void
    {
        $request = new Request(
            url: $this->settingsUrl,
            method: HttpMethod::POST,
            postParams: null
        );

        $response = $this->simulateRequest($request);

        $this->assertSame(HttpStatus::UNAUTHORIZED, $response->getStatus());
    }

    public function testPostAction_EmptyDisplayName(): void
    {
        $this->loginUser('empty-name-test-user', 'Empty Name Test');

        $request = new Request(
            url: $this->settingsUrl,
            method: HttpMethod::POST,
            postParams: ['displayName' => '']
        );
        $response = $this->simulateRequest($request);

        $this->assertEquals(HttpStatus::BAD_REQUEST, $response->getStatus());
    }

    public function testPostAction_NullPostParams(): void
    {
        $this->loginUser('null-params-test-user', 'Null Params Test');

        $request = new Request(
            url: $this->settingsUrl,
            method: HttpMethod::POST,
            postParams: null
        );
        $response = $this->simulateRequest($request);

        $this->assertEquals(HttpStatus::BAD_REQUEST, $response->getStatus());
    }
}
