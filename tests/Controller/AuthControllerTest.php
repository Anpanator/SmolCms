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
            password: $this->hashTestPassword($password),
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
        $this->assertSame(HttpContentType::JSON->value, $response->getHeaders()[HttpHeader::CONTENT_TYPE->value]);
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

    public function testPostAction_PublicLoginSucceedsWithAnExistingSession(): void
    {
        $loginName = 'already-authenticated-' . bin2hex(random_bytes(4));
        $this->loginUser($loginName, 'Already Authenticated');
        $sessionUserData = $this->sessionService->getUserData();
        self::assertNotNull($sessionUserData);

        // End the helper's simulated request while retaining its session for the next request.
        session_write_close();
        $request = new Request(
            url: new Url(protocol: 'https', host: 'localhost', path: '/login'),
            method: HttpMethod::POST,
            postParams: [
                'loginName' => $loginName,
                'password' => 'secure-password-123',
            ],
        );

        $response = $this->simulateRequest($request);

        self::assertSame(HttpStatus::OK, $response->getStatus());
        self::assertSame(HttpContentType::JSON->value, $response->getHeaders()[HttpHeader::CONTENT_TYPE->value]);
        self::assertSame(
            [
                'user' => [
                    'id' => $sessionUserData->id,
                    'displayName' => 'Already Authenticated',
                    'accessLevel' => AccessLevel::NOVICE->value,
                ],
            ],
            json_decode((string)$response->getContent(), true, JSON_THROW_ON_ERROR),
        );
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
        $this->assertSame(PHP_SESSION_NONE, session_status());
    }

    public function testPostAction_UnauthorizedOnWrongPasswordWithoutAuthenticatedSession(): void
    {
        $loginName = 'wrong-password-user';
        $correctPassword = 'secure-password-123';

        $user = new UserEntity(
            id: null,
            loginName: $loginName,
            password: $this->hashTestPassword($correctPassword),
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
        $this->assertSame(PHP_SESSION_NONE, session_status());
    }

    public function testGetSessionAction_ReturnsNullUserWhenAnonymous(): void
    {
        $request = new Request(
            url: new Url(protocol: 'https', host: 'localhost', path: '/api/session'),
            method: HttpMethod::GET,
        );

        $response = $this->simulateRequest($request);

        $this->assertEquals(HttpStatus::OK, $response->getStatus());
        $this->assertSame(HttpContentType::JSON->value, $response->getHeaders()[HttpHeader::CONTENT_TYPE->value]);
        $this->assertSame(
            ['user' => null, 'quickTagsEnabled' => false],
            json_decode((string)$response->getContent(), true),
        );
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
        $this->assertSame(HttpContentType::JSON->value, $response->getHeaders()[HttpHeader::CONTENT_TYPE->value]);
        $this->assertSame(
            [
                'user' => [
                    'id' => $sessionUserData->id,
                    'displayName' => 'Session User',
                    'accessLevel' => AccessLevel::NOVICE->value,
                ],
                'quickTagsEnabled' => false,
            ],
            json_decode((string)$response->getContent(), true)
        );
    }

    public function testQuickTagsToggleRequiresLoginPersistsWithinSessionAndResetsAfterLogout(): void
    {
        $anonymous = $this->simulateRequest(new Request(
            url: new Url(protocol: 'https', host: 'localhost', path: '/api/session/quick-tags'),
            method: HttpMethod::POST,
            postParams: ['enabled' => true],
        ));
        self::assertSame(HttpStatus::UNAUTHORIZED, $anonymous->getStatus());

        $loginName = 'quick-tags-session-' . bin2hex(random_bytes(4));
        $this->loginUser($loginName, 'Quick Tags User');
        self::assertFalse($this->sessionService->getQuickTagsEnabled());

        $enabled = $this->simulateRequest(new Request(
            url: new Url(protocol: 'https', host: 'localhost', path: '/api/session/quick-tags'),
            method: HttpMethod::POST,
            postParams: ['enabled' => true],
        ));
        self::assertSame(HttpStatus::OK, $enabled->getStatus());
        self::assertSame(
            ['quickTagsEnabled' => true],
            json_decode((string)$enabled->getContent(), true, JSON_THROW_ON_ERROR),
        );

        $session = $this->simulateRequest(new Request(
            url: new Url(protocol: 'https', host: 'localhost', path: '/api/session'),
            method: HttpMethod::GET,
        ));
        self::assertSame(true, json_decode((string)$session->getContent(), true, JSON_THROW_ON_ERROR)['quickTagsEnabled']);
        self::assertTrue($this->sessionService->getQuickTagsEnabled());

        $disabled = $this->simulateRequest(new Request(
            url: new Url(protocol: 'https', host: 'localhost', path: '/api/session/quick-tags'),
            method: HttpMethod::POST,
            postParams: ['enabled' => false],
        ));
        self::assertSame(
            ['quickTagsEnabled' => false],
            json_decode((string)$disabled->getContent(), true, JSON_THROW_ON_ERROR),
        );

        $enabledAgain = $this->simulateRequest(new Request(
            url: new Url(protocol: 'https', host: 'localhost', path: '/api/session/quick-tags'),
            method: HttpMethod::POST,
            postParams: ['enabled' => true],
        ));
        self::assertSame(HttpStatus::OK, $enabledAgain->getStatus());
        $this->simulateRequest(new Request(
            url: new Url(protocol: 'https', host: 'localhost', path: '/logout'),
            method: HttpMethod::POST,
        ));
        self::assertFalse($this->sessionService->getQuickTagsEnabled());

        $this->loginUser($loginName, 'Quick Tags User');
        $newSession = $this->simulateRequest(new Request(
            url: new Url(protocol: 'https', host: 'localhost', path: '/api/session'),
            method: HttpMethod::GET,
        ));
        self::assertSame(false, json_decode((string)$newSession->getContent(), true, JSON_THROW_ON_ERROR)['quickTagsEnabled']);
    }

    public function testQuickTagsToggleRejectsMissingOrNonBooleanEnabledValue(): void
    {
        $this->loginUser('quick-tags-invalid-' . bin2hex(random_bytes(4)), 'Quick Tags Invalid');

        foreach ([null, ['enabled' => null], ['enabled' => 'true'], ['enabled' => 1]] as $params) {
            $response = $this->simulateRequest(new Request(
                url: new Url(protocol: 'https', host: 'localhost', path: '/api/session/quick-tags'),
                method: HttpMethod::POST,
                postParams: $params,
            ));
            self::assertSame(HttpStatus::BAD_REQUEST, $response->getStatus());
        }
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

    public function testCrossOriginPostIsForbiddenBeforeControllerValidation(): void
    {
        $fetchMetadataResponse = $this->simulateRequest(new Request(
            url: new Url(protocol: 'https', host: 'localhost', path: '/logout'),
            method: HttpMethod::POST,
            headers: [HttpHeader::SEC_FETCH_SITE->value => 'cross-site'],
        ));
        self::assertSame(HttpStatus::FORBIDDEN, $fetchMetadataResponse->getStatus());

        $originResponse = $this->simulateRequest(new Request(
            url: new Url(protocol: 'https', host: 'localhost', path: '/logout'),
            method: HttpMethod::POST,
            headers: [HttpHeader::ORIGIN->value => 'https://evil.example'],
        ));
        self::assertSame(HttpStatus::FORBIDDEN, $originResponse->getStatus());
    }
}
