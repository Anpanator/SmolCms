<?php

declare(strict_types=1);

namespace SmolCms\Test\Controller;

use DateTime;
use SmolCms\Data\Business\Url;
use SmolCms\Data\Constant\ImageStatus;
use SmolCms\Data\Constant\HttpMethod;
use SmolCms\Data\Constant\HttpStatus;
use SmolCms\Data\Persistence\TagApprovalEntity;
use SmolCms\Data\Request\Request;
use SmolCms\Service\Core\Session\SessionService;
use SmolCms\Service\DB\ImageEntityService;
use SmolCms\Service\DB\TagApprovalService;
use SmolCms\Service\Image\ImagePathLayout;
use SmolCms\TestUtils\Attributes\Autowire;
use SmolCms\TestUtils\FunctionalTestCase;
use SmolCms\TestUtils\ImageTestUtils;

class ProfileControllerTest extends FunctionalTestCase
{
    #[Autowire]
    private SessionService $sessionService;

    #[Autowire]
    private TagApprovalService $tagApprovalService;

    #[Autowire]
    private ImageEntityService $imageEntityService;

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
            ['approvedTagSuggestions' => 3, 'recentUploads' => []],
            json_decode((string)$response->getContent(), true, JSON_THROW_ON_ERROR),
        );
    }

    public function testProfileReturnsOnlyTheUsersFifteenMostRecentProcessedUploads(): void
    {
        $this->loginUser('profile-uploads-' . bin2hex(random_bytes(5)), 'Profile Uploads');
        $userId = $this->sessionService->getUserData()->id;
        $uploads = [];
        for ($index = 0; $index < 17; $index++) {
            $status = in_array($index, [4, 16], true)
                ? ImageStatus::PENDING_REVIEW
                : ImageStatus::ACCEPTED;
            $uploadDate = new DateTime('2026-10-19 00:00:00');
            $uploadDate->modify('+' . $index . ' minutes');
            $uploads[] = ImageTestUtils::createImageEntity(
                $this->imageEntityService,
                userId: $userId,
                status: $status,
                uploadDate: $uploadDate,
                thumbFilename: ImageTestUtils::randomChecksum() . '.avif',
            );
        }

        ImageTestUtils::createImageEntity(
            $this->imageEntityService,
            userId: $userId,
            status: ImageStatus::PENDING,
            uploadDate: new DateTime('2026-10-20 12:00:00'),
        );
        ImageTestUtils::createImageEntity(
            $this->imageEntityService,
            userId: $userId + 100000,
            status: ImageStatus::ACCEPTED,
            uploadDate: new DateTime('2026-10-21 12:00:00'),
            thumbFilename: ImageTestUtils::randomChecksum() . '.avif',
        );

        $response = $this->simulateProfileRequest();
        $body = json_decode((string)$response->getContent(), true, JSON_THROW_ON_ERROR);

        self::assertSame(HttpStatus::OK, $response->getStatus());
        self::assertSame(0, $body['approvedTagSuggestions']);
        self::assertCount(15, $body['recentUploads']);
        self::assertSame(
            array_map(
                static fn($image): array => [
                    'id' => $image->getId(),
                    'thumbSrc' => '/public/img/thumb/'
                        . ImagePathLayout::relativePathForFileName($image->getThumbFilename()),
                    'status' => $image->getStatus()->value,
                    'uploadDate' => $image->getUploadDate()->format('Y-m-d H:i:s'),
                ],
                array_slice(array_reverse($uploads), 0, 15),
            ),
            $body['recentUploads'],
        );
        self::assertContains('pending_review', array_column($body['recentUploads'], 'status'));
    }

    private function simulateProfileRequest(): \SmolCms\Data\Response\Response
    {
        return $this->simulateRequest(new Request(
            url: new Url(protocol: 'https', host: 'localhost', path: '/api/profile'),
            method: HttpMethod::GET,
        ));
    }
}
