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

    public function testPostAction_SuccessfulUpdate(): void
    {
        $this->loginUser('update-test-user', 'Old Name');
        $userData = $this->sessionService->getUserData();
        $this->assertNotNull($userData);

        $request = new Request(
            url: $this->settingsUrl,
            method: HttpMethod::POST,
            postParams: ['displayName' => 'Updated Name']
        );
        $response = $this->simulateRequest($request);

        $this->assertEquals(HttpStatus::OK, $response->getStatus());
        $this->assertSame('application/json', $response->getHeaders()['Content-Type']);
        $this->assertSame(
            [
                'user' => [
                    'id' => $userData->id,
                    'displayName' => 'Updated Name',
                    'accessLevel' => $userData->accessLevel->value,
                ],
            ],
            json_decode((string)$response->getContent(), true)
        );

        $updatedUserData = $this->sessionService->getUserData();
        $this->assertNotNull($updatedUserData);
        $this->assertSame('Updated Name', $updatedUserData->displayName);

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
        $this->assertNull($response->getContent());
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
        $this->assertNull($response->getContent());
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
        $this->assertNull($response->getContent());
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
        $this->assertNull($response->getContent());
    }
}
