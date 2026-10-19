<?php

declare(strict_types=1);

namespace SmolCms\Test\Controller;

use DateTime;
use SmolCms\Data\Business\Url;
use SmolCms\Data\Constant\HttpMethod;
use SmolCms\Data\Constant\HttpStatus;
use SmolCms\Data\Persistence\TagApprovalEntity;
use SmolCms\Data\Request\Request;
use SmolCms\Service\Core\Session\SessionService;
use SmolCms\Service\DB\TagApprovalService;
use SmolCms\TestUtils\Attributes\Autowire;
use SmolCms\TestUtils\FunctionalTestCase;

class ProfileControllerTest extends FunctionalTestCase
{
    #[Autowire]
    private SessionService $sessionService;

    #[Autowire]
    private TagApprovalService $tagApprovalService;

    public function testProfileRequiresAuthenticationAndReturnsApprovedSuggestionCount(): void
    {
        $unauthorized = $this->simulateProfileRequest();
        self::assertSame(HttpStatus::UNAUTHORIZED, $unauthorized->getStatus());

        $this->loginUser('profile-user-' . bin2hex(random_bytes(5)), 'Profile User');
        $userId = $this->sessionService->getUserData()->id;
        for ($index = 0; $index < 3; $index++) {
            $this->tagApprovalService->saveAsNew(new TagApprovalEntity(
                id: null,
                tagId: $index + 1,
                userId: $userId,
                created: new DateTime(),
            ));
        }

        $response = $this->simulateProfileRequest();

        self::assertSame(HttpStatus::OK, $response->getStatus());
        self::assertSame(
            ['approvedTagSuggestions' => 3],
            json_decode((string)$response->getContent(), true, JSON_THROW_ON_ERROR),
        );
    }

    private function simulateProfileRequest(): \SmolCms\Data\Response\Response
    {
        return $this->simulateRequest(new Request(
            url: new Url(protocol: 'https', host: 'localhost', path: '/api/profile'),
            method: HttpMethod::GET,
        ));
    }
}
