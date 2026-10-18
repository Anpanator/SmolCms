<?php
declare(strict_types=1);

namespace SmolCms\Test\Controller;

use DateTime;
use SmolCms\Data\Business\Url;
use SmolCms\Data\Constant\AccessLevel;
use SmolCms\Data\Constant\HttpContentType;
use SmolCms\Data\Constant\HttpHeader;
use SmolCms\Data\Constant\HttpMethod;
use SmolCms\Data\Constant\HttpStatus;
use SmolCms\Data\Persistence\UserEntity;
use SmolCms\Data\Request\Request;
use SmolCms\Service\DB\UserService;
use SmolCms\TestUtils\Attributes\Autowire;
use SmolCms\TestUtils\FunctionalTestCase;

class RegisterControllerTest extends FunctionalTestCase
{
    #[Autowire]
    private UserService $userService;

    private Url $url;

    protected function setUp(): void
    {
        parent::setUp();
        $this->url = new Url(
            protocol: 'https',
            host: 'localhost',
            path: '/register'
        );
    }

    public function testPostAction_SuccessfulRegistration(): void
    {
        $request = new Request(
            url: $this->url,
            method: HttpMethod::POST,
            postParams: [
                'email' => 'user@example.com',
                'password' => 'long-enough-password',
                'passwordRepeat' => 'long-enough-password',
                'displayName' => 'Test User',
                'loginName' => 'testuser',
            ]
        );

        $response = $this->simulateRequest($request);

        $this->assertEquals(HttpStatus::CREATED, $response->getStatus());
        $this->assertSame(HttpContentType::JSON->value, $response->getHeaders()[HttpHeader::CONTENT_TYPE->value]);
        $this->assertSame(['created' => true], json_decode((string)$response->getContent(), true));

        $savedUser = $this->userService->findOneByLoginName('testuser');
        $this->assertNotNull($savedUser);
        $this->assertSame('Test User', $savedUser->getDisplayName());
        $this->assertSame('testuser', $savedUser->getLoginName());
    }

    public function testPostAction_PasswordMismatch(): void
    {
        $request = new Request(
            url: $this->url,
            method: HttpMethod::POST,
            postParams: [
                'email' => 'user@example.com',
                'password' => 'long-enough-password',
                'passwordRepeat' => 'different-password',
                'displayName' => 'Test User',
                'loginName' => 'testuser',
            ]
        );

        $response = $this->simulateRequest($request);

        $this->assertEquals(HttpStatus::BAD_REQUEST, $response->getStatus());
        $this->assertNull($response->getContent());
    }

    public function testPostAction_DuplicateLoginName(): void
    {
        $existingUser = new UserEntity(
            id: null,
            loginName: 'duplicate-user',
            password: 'some-hashed-password',
            displayName: 'Existing User',
            state: 'active',
            registerDate: new DateTime(),
            lastLoginDate: null,
            accessLevel: AccessLevel::NOVICE,
        );
        $this->userService->saveAsNew($existingUser);

        $request = new Request(
            url: $this->url,
            method: HttpMethod::POST,
            postParams: [
                'email' => 'other@example.com',
                'password' => 'long-enough-password',
                'passwordRepeat' => 'long-enough-password',
                'displayName' => 'Test User',
                'loginName' => 'duplicate-user',
            ]
        );

        $response = $this->simulateRequest($request);

        $this->assertEquals(HttpStatus::CONFLICT, $response->getStatus());
    }

    public function testPostAction_NullPostParams(): void
    {
        $request = new Request(
            url: $this->url,
            method: HttpMethod::POST,
            postParams: null
        );

        $response = $this->simulateRequest($request);

        $this->assertEquals(HttpStatus::BAD_REQUEST, $response->getStatus());
    }

    public function testPostAction_ShortPassword(): void
    {
        $request = new Request(
            url: $this->url,
            method: HttpMethod::POST,
            postParams: [
                'email' => 'user@example.com',
                'password' => 'short',
                'passwordRepeat' => 'short',
                'displayName' => 'Test User',
                'loginName' => 'testuser',
            ]
        );

        $response = $this->simulateRequest($request);

        $this->assertEquals(HttpStatus::BAD_REQUEST, $response->getStatus());
    }

    public function testPostAction_InvalidEmail(): void
    {
        $request = new Request(
            url: $this->url,
            method: HttpMethod::POST,
            postParams: [
                'email' => 'not-an-email',
                'password' => 'long-enough-password',
                'passwordRepeat' => 'long-enough-password',
                'displayName' => 'Test User',
                'loginName' => 'testuser',
            ]
        );

        $response = $this->simulateRequest($request);

        $this->assertEquals(HttpStatus::BAD_REQUEST, $response->getStatus());
    }
}
