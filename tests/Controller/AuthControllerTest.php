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

        $request = new Request(
            url: new Url(protocol: 'https', host: 'localhost', path: '/login'),
            method: HttpMethod::POST,
            postParams: [
                'loginName' => $loginName,
                'password' => $password
            ]
        );

        $response = $this->simulateRequest($request);

        $this->assertEquals(HttpStatus::OK, $response->getStatus());
        $this->assertSame('application/json', $response->getHeaders()['Content-Type']);
        $this->assertSame(
            [
                'user' => [
                    'id' => $user->getId(),
                    'displayName' => 'Admin User',
                    'accessLevel' => AccessLevel::FELLOW->value,
                ],
            ],
            json_decode((string)$response->getContent(), true)
        );

        $sessionUserData = $this->sessionService->getUserData();
        $this->assertNotNull($sessionUserData);
        $this->assertSame($user->getId(), $sessionUserData->id);
        $this->assertSame($user->getDisplayName(), $sessionUserData->displayName);
        $this->assertSame($user->getAccessLevel(), $sessionUserData->accessLevel);
    }

    public function testPostAction_NoErrorOnEmptyPostParams(): void
    {
        $request = new Request(
            url: new Url(protocol: 'https', host: 'localhost', path: '/login'),
            method: HttpMethod::POST,
            postParams: null
        );

        $response = $this->simulateRequest($request);

        $this->assertEquals(HttpStatus::BAD_REQUEST, $response->getStatus());
        $this->assertNull($response->getContent());
    }

    public function testPostAction_UnauthorizedOnInvalidCredentials(): void
    {
        $request = new Request(
            url: new Url(protocol: 'https', host: 'localhost', path: '/login'),
            method: HttpMethod::POST,
            postParams: [
                'loginName' => 'wrong-user',
                'password' => 'wrong-password'
            ]
        );

        $response = $this->simulateRequest($request);

        $this->assertEquals(HttpStatus::UNAUTHORIZED, $response->getStatus());
        $this->assertNull($response->getContent());
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
        $this->assertNull($response->getContent());
        $this->assertNull($this->sessionService->getUserData());
    }

    public function testGetSessionAction_ReturnsNullUserWhenAnonymous(): void
    {
        $request = new Request(
            url: new Url(protocol: 'https', host: 'localhost', path: '/api/session'),
            method: HttpMethod::GET,
        );

        $response = $this->simulateRequest($request);

        $this->assertEquals(HttpStatus::OK, $response->getStatus());
        $this->assertSame('application/json', $response->getHeaders()['Content-Type']);
        $this->assertSame(['user' => null], json_decode((string)$response->getContent(), true));
    }

    public function testGetSessionAction_ReturnsUserDataWhenLoggedIn(): void
    {
        $this->loginUser('session-user', 'Session User');
        $sessionUserData = $this->sessionService->getUserData();
        $this->assertNotNull($sessionUserData);

        $request = new Request(
            url: new Url(protocol: 'https', host: 'localhost', path: '/api/session'),
            method: HttpMethod::GET,
        );

        $response = $this->simulateRequest($request);

        $this->assertEquals(HttpStatus::OK, $response->getStatus());
        $this->assertSame('application/json', $response->getHeaders()['Content-Type']);
        $this->assertSame(
            [
                'user' => [
                    'id' => $sessionUserData->id,
                    'displayName' => 'Session User',
                    'accessLevel' => AccessLevel::NOVICE->value,
                ],
            ],
            json_decode((string)$response->getContent(), true)
        );
    }

    public function testLogoutAction_ReturnsNoContentAndDestroysSession(): void
    {
        $this->loginUser('logout-test-user', 'Admin User');
        $this->assertNotNull($this->sessionService->getUserData());

        $logoutRequest = new Request(
            url: new Url(protocol: 'https', host: 'localhost', path: '/logout'),
            method: HttpMethod::POST,
            postParams: null
        );

        $response = $this->simulateRequest($logoutRequest);

        $this->assertEquals(HttpStatus::NO_CONTENT, $response->getStatus());
        $this->assertNull($response->getContent());
        $this->assertNull($this->sessionService->getUserData());
    }

    public function testLogoutAction_ReturnsNoContentWithoutActiveSession(): void
    {
        $logoutRequest = new Request(
            url: new Url(protocol: 'https', host: 'localhost', path: '/logout'),
            method: HttpMethod::POST,
        );

        $response = $this->simulateRequest($logoutRequest);

        $this->assertSame(HttpStatus::NO_CONTENT, $response->getStatus());
        $this->assertNull($response->getContent());
        $this->assertNull($this->sessionService->getUserData());
    }
}
