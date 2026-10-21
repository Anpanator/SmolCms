<?php

declare(strict_types=1);

namespace SmolCms\Test\Controller;

use DateTime;
use PHPUnit\Framework\Attributes\DataProvider;
use SmolCms\Data\Business\Url;
use SmolCms\Data\Constant\AccessLevel;
use SmolCms\Data\Constant\AuditActionType;
use SmolCms\Data\Constant\HttpMethod;
use SmolCms\Data\Constant\HttpStatus;
use SmolCms\Data\Constant\ImageStatus;
use SmolCms\Data\Constant\RouteEnum;
use SmolCms\Data\Persistence\TagApprovalEntity;
use SmolCms\Data\Persistence\UserEntity;
use SmolCms\Data\Request\Request;
use SmolCms\Service\Core\Session\SessionService;
use SmolCms\Service\DB\AuditLogService;
use SmolCms\Service\DB\ImageEntityService;
use SmolCms\Service\DB\TagApprovalService;
use SmolCms\Service\DB\UserService;
use SmolCms\Service\Image\ImagePathLayout;
use SmolCms\TestUtils\Attributes\Autowire;
use SmolCms\TestUtils\FunctionalTestCase;
use SmolCms\TestUtils\ImageTestUtils;
use SmolCms\TestUtils\LoginWithAccessLevel;

class ProfileControllerTest extends FunctionalTestCase
{
    use LoginWithAccessLevel;

    #[Autowire]
    private SessionService $sessionService;

    #[Autowire]
    private TagApprovalService $tagApprovalService;

    #[Autowire]
    private ImageEntityService $imageEntityService;

    #[Autowire]
    private UserService $userService;

    #[Autowire]
    private AuditLogService $auditLogService;

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

    public function testPublicProfileReturnsUserDetailsAndApprovedTagSuggestionCount(): void
    {
        $target = $this->createUser(
            'public-profile-' . bin2hex(random_bytes(5)),
            'Public Profile User',
            AccessLevel::FELLOW,
            new DateTime('2026-10-21 07:08:09'),
        );
        $targetId = self::userId($target);
        for ($index = 0; $index < 2; $index++) {
            $this->tagApprovalService->saveAsNew(new TagApprovalEntity(
                id: null,
                tagId: $index + 1,
                userId: $targetId,
                created: new DateTime('2026-10-21 07:09:00'),
            ));
        }

        $response = $this->simulateUserProfileRequest(['id' => (string)$targetId]);

        self::assertSame(HttpStatus::OK, $response->getStatus());
        self::assertSame(
            [
                'user' => [
                    'id' => $targetId,
                    'displayName' => 'Public Profile User',
                    'accessLevel' => AccessLevel::FELLOW->value,
                    'registerDate' => '2026-10-21 07:08:09',
                ],
                'approvedTagSuggestions' => 2,
                'recentUploads' => [],
                'modActions' => [],
            ],
            json_decode((string)$response->getContent(), true, JSON_THROW_ON_ERROR),
        );
    }

    public function testAnonymousPublicProfileShowsOnlyFifteenNewestAcceptedUploads(): void
    {
        $target = $this->createUser(
            'public-profile-uploads-' . bin2hex(random_bytes(5)),
            'Public Upload User',
        );
        $targetId = self::userId($target);
        $uploads = [];
        for ($index = 0; $index < 17; $index++) {
            $uploadDate = new DateTime('2026-10-19 00:00:00');
            $uploadDate->modify('+' . $index . ' minutes');
            $uploads[] = ImageTestUtils::createImageEntity(
                $this->imageEntityService,
                userId: $targetId,
                status: ImageStatus::ACCEPTED,
                uploadDate: $uploadDate,
                thumbFilename: ImageTestUtils::randomChecksum() . '.avif',
            );
        }
        ImageTestUtils::createImageEntity(
            $this->imageEntityService,
            userId: $targetId,
            status: ImageStatus::PENDING_REVIEW,
            uploadDate: new DateTime('2026-10-20 00:00:00'),
            thumbFilename: ImageTestUtils::randomChecksum() . '.avif',
        );
        ImageTestUtils::createImageEntity(
            $this->imageEntityService,
            userId: $targetId,
            status: ImageStatus::PENDING,
            uploadDate: new DateTime('2026-10-21 00:00:00'),
            thumbFilename: ImageTestUtils::randomChecksum() . '.avif',
        );

        $body = $this->getUserProfileBody($targetId);

        self::assertCount(15, $body['recentUploads']);
        self::assertSame(
            array_map(
                static fn($image): array => [
                    'id' => $image->getId(),
                    'thumbSrc' => '/public/img/thumb/'
                        . ImagePathLayout::relativePathForFileName($image->getThumbFilename()),
                    'status' => ImageStatus::ACCEPTED->value,
                    'uploadDate' => $image->getUploadDate()->format('Y-m-d H:i:s'),
                ],
                array_slice(array_reverse($uploads), 0, 15),
            ),
            $body['recentUploads'],
        );
    }

    public function testPublicProfileShowsOwnPendingReviewUploadsAndHidesOtherUsersPendingUploads(): void
    {
        $this->loginUser('profile-visibility-' . bin2hex(random_bytes(5)), 'Profile Visibility User');
        $viewer = $this->sessionService->getUserData();
        self::assertNotNull($viewer);

        $ownAccepted = ImageTestUtils::createImageEntity(
            $this->imageEntityService,
            userId: $viewer->id,
            status: ImageStatus::ACCEPTED,
            uploadDate: new DateTime('2026-10-19 10:00:00'),
            thumbFilename: ImageTestUtils::randomChecksum() . '.avif',
        );
        $ownPendingReview = ImageTestUtils::createImageEntity(
            $this->imageEntityService,
            userId: $viewer->id,
            status: ImageStatus::PENDING_REVIEW,
            uploadDate: new DateTime('2026-10-19 11:00:00'),
            thumbFilename: ImageTestUtils::randomChecksum() . '.avif',
        );
        ImageTestUtils::createImageEntity(
            $this->imageEntityService,
            userId: $viewer->id,
            status: ImageStatus::PENDING,
            uploadDate: new DateTime('2026-10-19 12:00:00'),
            thumbFilename: ImageTestUtils::randomChecksum() . '.avif',
        );

        $otherUser = $this->createUser(
            'profile-visibility-other-' . bin2hex(random_bytes(5)),
            'Other Profile User',
        );
        $otherUserId = self::userId($otherUser);
        $otherAccepted = ImageTestUtils::createImageEntity(
            $this->imageEntityService,
            userId: $otherUserId,
            status: ImageStatus::ACCEPTED,
            uploadDate: new DateTime('2026-10-19 10:00:00'),
            thumbFilename: ImageTestUtils::randomChecksum() . '.avif',
        );
        ImageTestUtils::createImageEntity(
            $this->imageEntityService,
            userId: $otherUserId,
            status: ImageStatus::PENDING_REVIEW,
            uploadDate: new DateTime('2026-10-19 11:00:00'),
            thumbFilename: ImageTestUtils::randomChecksum() . '.avif',
        );

        $ownProfile = $this->getUserProfileBody($viewer->id);
        $otherProfile = $this->getUserProfileBody($otherUserId);

        self::assertSame(
            [$ownPendingReview->getId(), $ownAccepted->getId()],
            array_column($ownProfile['recentUploads'], 'id'),
        );
        self::assertSame(
            [ImageStatus::PENDING_REVIEW->value, ImageStatus::ACCEPTED->value],
            array_column($ownProfile['recentUploads'], 'status'),
        );
        self::assertSame([$otherAccepted->getId()], array_column($otherProfile['recentUploads'], 'id'));
        self::assertSame([ImageStatus::ACCEPTED->value], array_column($otherProfile['recentUploads'], 'status'));
    }

    public function testPublicProfileReturnsNotFoundForUnknownId(): void
    {
        $response = $this->simulateUserProfileRequest(['id' => (string)PHP_INT_MAX]);

        self::assertSame(HttpStatus::NOT_FOUND, $response->getStatus());
        self::assertNull($response->getContent());
    }

    public static function malformedUserProfileIdProvider(): array
    {
        return [
            'missing query' => [null],
            'missing id' => [[]],
            'null id' => [['id' => null]],
            'non-string id' => [['id' => 1]],
            'array id' => [['id' => ['1']]],
            'empty id' => [['id' => '']],
            'non-digit id' => [['id' => '1x']],
            'zero id' => [['id' => '0']],
            'negative id' => [['id' => '-1']],
            'float id' => [['id' => '1.5']],
            'overflow id' => [['id' => str_repeat('9', strlen((string)PHP_INT_MAX) + 1)]],
        ];
    }

    #[DataProvider('malformedUserProfileIdProvider')]
    public function testPublicProfileReturnsBadRequestForMalformedId(?array $params): void
    {
        $response = $this->simulateUserProfileRequest($params);

        self::assertSame(HttpStatus::BAD_REQUEST, $response->getStatus());
        self::assertNull($response->getContent());
    }

    public function testModActionsAreVisibleOnlyToModeratorsOfLowerRankedOtherUsers(): void
    {
        $suffix = bin2hex(random_bytes(5));
        $lowerTarget = $this->createUser('profile-mod-lower-' . $suffix, 'Lower Ranked User');
        $lowerTargetId = self::userId($lowerTarget);
        $this->auditLogService->record($lowerTargetId, AuditActionType::TAG_ADDED, 'lower-oldest');
        $this->auditLogService->record($lowerTargetId, AuditActionType::TAG_REMOVED, 'lower-newest');

        $equalTarget = $this->createUser(
            'profile-mod-equal-' . $suffix,
            'Equal Ranked User',
            AccessLevel::MOD,
        );
        $equalTargetId = self::userId($equalTarget);
        $this->auditLogService->record($equalTargetId, AuditActionType::TAG_ADDED, 'equal-entry');

        $higherTarget = $this->createUser(
            'profile-mod-higher-' . $suffix,
            'Higher Ranked User',
            AccessLevel::ADMIN,
        );
        $higherTargetId = self::userId($higherTarget);
        $this->auditLogService->record($higherTargetId, AuditActionType::TAG_ADDED, 'higher-entry');

        $anonymousProfile = $this->getUserProfileBody($lowerTargetId);
        self::assertSame([], $anonymousProfile['modActions']);

        $this->loginUserWithAccessLevel(
            'profile-mod-viewer-fellow-' . $suffix,
            'Fellow Viewer',
            AccessLevel::FELLOW,
        );
        $fellowProfile = $this->getUserProfileBody($lowerTargetId);
        self::assertSame([], $fellowProfile['modActions']);

        $this->loginUserWithAccessLevel(
            'profile-mod-viewer-mod-' . $suffix,
            'Moderator Viewer',
            AccessLevel::MOD,
        );
        $lowerProfile = $this->getUserProfileBody($lowerTargetId);
        self::assertSame(['lower-newest', 'lower-oldest'], array_column($lowerProfile['modActions'], 'context'));
        self::assertSame([], $this->getUserProfileBody($equalTargetId)['modActions']);
        self::assertSame([], $this->getUserProfileBody($higherTargetId)['modActions']);

        $this->loginUserWithAccessLevel(
            'profile-mod-viewer-self-' . $suffix,
            'Moderator Self Viewer',
            AccessLevel::MOD,
        );
        $selfUser = $this->userService->findOneByLoginName('profile-mod-viewer-self-' . $suffix);
        self::assertNotNull($selfUser);
        $selfUserId = self::userId($selfUser);
        $this->auditLogService->record($selfUserId, AuditActionType::TAG_ADDED, 'self-entry');

        self::assertSame([], $this->getUserProfileBody($selfUserId)['modActions']);
    }

    public function testDemotedModeratorCannotSeeModActionsWithStaleSession(): void
    {
        $suffix = bin2hex(random_bytes(5));
        $loginName = 'profile-mod-demoted-viewer-' . $suffix;
        $this->loginUserWithAccessLevel($loginName, 'Demoted Moderator Viewer', AccessLevel::MOD);
        $sessionViewer = $this->sessionService->getUserData();
        self::assertNotNull($sessionViewer);

        $target = $this->createUser(
            'profile-mod-demoted-target-' . $suffix,
            'Lower Ranked Target',
            AccessLevel::NOVICE,
        );
        $targetId = self::userId($target);
        $this->auditLogService->record($targetId, AuditActionType::TAG_ADDED, 'demoted-oldest');
        $this->auditLogService->record($targetId, AuditActionType::TAG_REMOVED, 'demoted-newest');

        self::assertSame(
            ['demoted-newest', 'demoted-oldest'],
            array_column($this->getUserProfileBody($targetId)['modActions'], 'context'),
        );

        $viewer = $this->userService->findOneById($sessionViewer->id);
        self::assertNotNull($viewer);
        $viewer->setAccessLevel(AccessLevel::FELLOW);
        $this->userService->update($viewer);

        self::assertSame(AccessLevel::MOD, $this->sessionService->getUserData()?->accessLevel);
        self::assertSame([], $this->getUserProfileBody($targetId)['modActions']);
    }

    private function simulateProfileRequest(): \SmolCms\Data\Response\Response
    {
        return $this->simulateRequest(new Request(
            url: new Url(protocol: 'https', host: 'localhost', path: RouteEnum::API_PROFILE->value),
            method: HttpMethod::GET,
        ));
    }

    private function simulateUserProfileRequest(?array $params = null): \SmolCms\Data\Response\Response
    {
        return $this->simulateRequest(new Request(
            url: new Url(protocol: 'https', host: 'localhost', path: RouteEnum::API_USER_PROFILE->value),
            method: HttpMethod::GET,
            getParams: $params,
        ));
    }

    private function getUserProfileBody(int $userId): array
    {
        $response = $this->simulateUserProfileRequest(['id' => (string)$userId]);
        self::assertSame(HttpStatus::OK, $response->getStatus());

        return json_decode((string)$response->getContent(), true, JSON_THROW_ON_ERROR);
    }

    private function createUser(
        string $loginName,
        string $displayName,
        AccessLevel $accessLevel = AccessLevel::NOVICE,
        ?DateTime $registerDate = null,
    ): UserEntity {
        $user = new UserEntity(
            id: null,
            loginName: $loginName,
            password: 'hashed-password',
            displayName: $displayName,
            state: 'active',
            registerDate: $registerDate ?? new DateTime('2026-10-20 12:00:00'),
            lastLoginDate: null,
            accessLevel: $accessLevel,
        );
        $this->userService->saveAsNew($user);
        return $user;
    }

    private static function userId(UserEntity $user): int
    {
        return $user->getId() ?? throw new \LogicException('Persisted test user must have an id.');
    }
}
