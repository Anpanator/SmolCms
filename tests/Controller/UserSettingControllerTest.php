<?php
declare(strict_types=1);

namespace SmolCms\Test\Controller;

use DateTime;
use SmolCms\Data\Business\Url;
use SmolCms\Data\Constant\HttpMethod;
use SmolCms\Data\Constant\HttpStatus;
use SmolCms\Data\Persistence\UserEntity;
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

        $this->assertEquals(HttpStatus::INTERNAL_SERVER_ERROR, $response->getStatus());
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

    private function loginUser(string $loginName, string $displayName): void
    {
        $password = 'secure-password-123';

        $user = new UserEntity(
            id: null,
            loginName: $loginName,
            password: password_hash($password, PASSWORD_DEFAULT),
            displayName: $displayName,
            state: 'active',
            registerDate: new DateTime(),
            lastLoginDate: null
        );
        $this->userService->saveAsNew($user);

        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION = [];
            session_destroy();
        }

        $loginUrl = new Url(protocol: 'https', host: 'localhost', path: '/login');
        $loginRequest = new Request(
            url: $loginUrl,
            method: HttpMethod::POST,
            postParams: [
                'loginName' => $loginName,
                'password' => $password,
            ]
        );
        $this->simulateRequest($loginRequest);
    }
}
