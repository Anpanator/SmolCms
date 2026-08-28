<?php
declare(strict_types=1);

namespace SmolCms\Test\Controller;

use DateTime;
use SmolCms\Data\Business\Url;
use SmolCms\Data\Constant\AccessLevel;
use SmolCms\Data\Constant\HttpMethod;
use SmolCms\Data\Constant\HttpStatus;
use SmolCms\Data\Persistence\UserEntity;
use SmolCms\Data\Request\Request;
use SmolCms\Service\Core\Session\SessionService;
use SmolCms\Service\DB\UserService;
use SmolCms\TestUtils\Attributes\Autowire;
use SmolCms\TestUtils\FunctionalTestCase;

class AuthControllerTest extends FunctionalTestCase
{
    #[Autowire]
    private UserService $userService;

    #[Autowire]
    private SessionService $sessionService;

    protected function setUp(): void
    {
        parent::setUp();
    }

    public function testPostAction_SuccessfulLogin(): void
    {
        $loginName = 'admin';
        $password = 'secure-password-123';

        $user = new UserEntity(
            id: null,
            loginName: $loginName,
            password: password_hash($password, PASSWORD_DEFAULT),
            displayName: 'Admin User',
            state: 'active',
            registerDate: new DateTime(),
            lastLoginDate: null,
            accessLevel: AccessLevel::FELLOW,
        );
        $this->userService->saveAsNew($user);

        $url = new Url(
            protocol: 'https',
            host: 'localhost',
            path: '/login'
        );

        $postParams = [
            'loginName' => $loginName,
            'password' => $password
        ];

        $request = new Request(
            url: $url,
            method: HttpMethod::POST,
            postParams: $postParams
        );

        $response = $this->simulateRequest($request);

        $this->assertEquals(HttpStatus::SEE_OTHER, $response->getStatus());
        $this->assertArrayHasKey('Location', $response->getHeaders());
        $this->assertSame('/', $response->getHeaders()['Location']);

        $sessionUserData = $this->sessionService->getUserData();
        $this->assertNotNull($sessionUserData);
        $this->assertSame($user->getId(), $sessionUserData->id);
        $this->assertSame($user->getDisplayName(), $sessionUserData->displayName);
        $this->assertSame($user->getAccessLevel(), $sessionUserData->accessLevel);
    }

    public function testPostAction_NoErrorOnEmptyPostParams(): void
    {
        $url = new Url(
            protocol: 'https',
            host: 'localhost',
            path: '/login'
        );

        $request = new Request(
            url: $url,
            method: HttpMethod::POST,
            postParams: null
        );

        $response = $this->simulateRequest($request);

        $this->assertEquals(HttpStatus::BAD_REQUEST, $response->getStatus());
    }

    public function testPostAction_UnauthorizedOnInvalidCredentials(): void
    {
        $url = new Url(
            protocol: 'https',
            host: 'localhost',
            path: '/login'
        );

        $request = new Request(
            url: $url,
            method: HttpMethod::POST,
            postParams: [
                'loginName' => 'wrong-user',
                'password' => 'wrong-password'
            ]
        );

        $response = $this->simulateRequest($request);

        $this->assertEquals(HttpStatus::UNAUTHORIZED->value, $response->getStatus()->value);
    }

    public function testPostAction_UnauthorizedOnWrongPasswordWithoutAuthenticatedSession(): void
    {
        $loginName = 'wrong-password-user';
        $correctPassword = 'secure-password-123';

        $user = new UserEntity(
            id: null,
            loginName: $loginName,
            password: password_hash($correctPassword, PASSWORD_DEFAULT),
            displayName: 'Wrong Password User',
            state: 'active',
            registerDate: new DateTime(),
            lastLoginDate: null,
            accessLevel: AccessLevel::NOVICE,
        );
        $this->userService->saveAsNew($user);

        $request = new Request(
            url: new Url(protocol: 'https', host: 'localhost', path: '/login'),
            method: HttpMethod::POST,
            postParams: [
                'loginName' => $loginName,
                'password' => 'incorrect-password',
            ]
        );

        $response = $this->simulateRequest($request);

        $this->assertSame(HttpStatus::UNAUTHORIZED, $response->getStatus());
        $this->assertNull($this->sessionService->getUserData());
    }

    public function testLogoutAction_RedirectsToStartPage(): void
    {
        $loginName = 'logout-test-user';
        $password = 'secure-password-123';

        $user = new UserEntity(
            id: null,
            loginName: $loginName,
            password: password_hash($password, PASSWORD_DEFAULT),
            displayName: 'Admin User',
            state: 'active',
            registerDate: new DateTime(),
            lastLoginDate: null,
            accessLevel: AccessLevel::NOVICE,
        );
        $this->userService->saveAsNew($user);

        $loginUrl = new Url(
            protocol: 'https',
            host: 'localhost',
            path: '/login'
        );

        $loginRequest = new Request(
            url: $loginUrl,
            method: HttpMethod::POST,
            postParams: [
                'loginName' => $loginName,
                'password' => $password
            ]
        );

        $this->simulateRequest($loginRequest);
        $this->assertNotNull($this->sessionService->getUserData());

        $logoutUrl = new Url(
            protocol: 'https',
            host: 'localhost',
            path: '/logout'
        );

        $logoutRequest = new Request(
            url: $logoutUrl,
            method: HttpMethod::POST,
            postParams: null
        );

        $response = $this->simulateRequest($logoutRequest);

        $this->assertEquals(HttpStatus::SEE_OTHER, $response->getStatus());
        $this->assertArrayHasKey('Location', $response->getHeaders());
        $this->assertSame('/', $response->getHeaders()['Location']);
        $this->assertNull($this->sessionService->getUserData());
    }

    public function testLogoutAction_RedirectsToStartPageWithoutActiveSession(): void
    {
        $logoutRequest = new Request(
            url: new Url(protocol: 'https', host: 'localhost', path: '/logout'),
            method: HttpMethod::POST,
        );

        $response = $this->simulateRequest($logoutRequest);

        $this->assertSame(HttpStatus::SEE_OTHER, $response->getStatus());
        $this->assertArrayHasKey('Location', $response->getHeaders());
        $this->assertSame('/', $response->getHeaders()['Location']);
        $this->assertNull($this->sessionService->getUserData());
    }
}
