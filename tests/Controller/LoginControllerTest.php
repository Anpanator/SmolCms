<?php
declare(strict_types=1);

namespace SmolCms\Test\Controller;

use SmolCms\Data\Business\Url;
use SmolCms\Data\Constant\HttpMethod;
use SmolCms\Data\Constant\HttpStatus;
use SmolCms\Data\Request\Request;
use SmolCms\Service\DB\UserService;
use SmolCms\TestUtils\Attributes\Autowire;
use SmolCms\TestUtils\FunctionalTestCase;

class LoginControllerTest extends FunctionalTestCase
{
    #[Autowire]
    private UserService $userService;

    public function testPostAction_SuccessfulLogin(): void
    {
        $url = new Url(
            protocol: 'https',
            host: 'localhost',
            path: '/login'
        );

        $postParams = [
            'loginName' => 'admin',
            'password' => 'secure-password-123'
        ];

        $request = new Request(
            url: $url,
            method: HttpMethod::POST,
            postParams: $postParams
        );

        $response = $this->applicationCore->simulateRequest($request);

        $this->assertEquals(HttpStatus::OK, $response->getStatus());
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

        $this->assertEquals(HttpStatus::OK, $response->getStatus());
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
