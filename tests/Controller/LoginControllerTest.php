<?php
declare(strict_types=1);

namespace SmolCms\Test\Controller;

use DateTime;
use PDO;
use SmolCms\Data\Business\Url;
use SmolCms\Data\Constant\HttpMethod;
use SmolCms\Data\Constant\HttpStatus;
use SmolCms\Data\Persistence\UserEntity;
use SmolCms\Data\Request\Request;
use SmolCms\Service\DB\UserService;
use SmolCms\TestUtils\Attributes\Autowire;
use SmolCms\TestUtils\FunctionalTestCase;

class LoginControllerTest extends FunctionalTestCase
{
    #[Autowire]
    private UserService $userService;
    #[Autowire]
    private PDO $pdo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->pdo->exec('TRUNCATE TABLE user');
    }

    protected function tearDown(): void
    {
        $this->pdo->exec('TRUNCATE TABLE user');
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
            lastLoginDate: null
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

        $response = $this->applicationCore->simulateRequest($request);

        $this->assertEquals(HttpStatus::SEE_OTHER, $response->getStatus());
        $this->assertArrayHasKey('Location', $response->getHeaders());
        $this->assertSame('/', $response->getHeaders()['Location']);
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

        $response = $this->applicationCore->simulateRequest($request);

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

        $response = $this->applicationCore->simulateRequest($request);

        $this->assertEquals(HttpStatus::UNAUTHORIZED->value, $response->getStatus()->value);
    }
}
