<?php

declare(strict_types=1);

namespace SmolCms\Test\Controller;

use SmolCms\Data\Business\Url;
use SmolCms\Data\Constant\AuditActionType;
use SmolCms\Data\Constant\HttpMethod;
use SmolCms\Data\Constant\HttpStatus;
use SmolCms\Data\Constant\ImageStatus;
use SmolCms\Data\Constant\TagInputLimits;
use SmolCms\Data\Request\Request;
use SmolCms\Data\Response\Response;
use PDO;
use SmolCms\Service\Core\Session\SessionService;
use SmolCms\Service\DB\AuditLogService;
use SmolCms\Service\DB\ImageEntityService;
use SmolCms\Service\Tag\TagService;
use SmolCms\Service\Tag\UserTagUsageService;
use SmolCms\TestUtils\Attributes\Autowire;
use SmolCms\TestUtils\FunctionalTestCase;
use SmolCms\TestUtils\ImageTestUtils;
use SmolCms\TestUtils\LoginWithAccessLevel;

class ImageTagControllerTest extends FunctionalTestCase
{
    use LoginWithAccessLevel;

    #[Autowire]
    private ImageEntityService $imageEntityService;

    #[Autowire]
    private TagService $tagService;

    #[Autowire]
    private AuditLogService $auditLogService;

    #[Autowire]
    private SessionService $sessionService;

    #[Autowire]
    private UserTagUsageService $userTagUsageService;

    #[Autowire]
    private PDO $pdo;

    public function testAddActionRequiresAuthenticationAppliesOnlyExistingTagsAndAuditsNewAssociations(): void
    {
        $unauthorized = $this->simulateImageTagsPost('/api/image/tags', ['imageId' => 1, 'tags' => 'cat']);
        self::assertSame(HttpStatus::UNAUTHORIZED, $unauthorized->getStatus());

        $loginName = 'image-tag-add-' . bin2hex(random_bytes(5));
        $this->loginUser($loginName, 'Image Tag Add');
        $userId = $this->sessionService->getUserData()->id;
        $image = ImageTestUtils::createImageEntity(
            $this->imageEntityService,
            userId: $userId,
            status: ImageStatus::ACCEPTED,
        );
        $tagName = 'known-' . bin2hex(random_bytes(4));
        $unknownName = 'unknown-' . bin2hex(random_bytes(4));
        $this->tagService->createTag($tagName);

        $response = $this->simulateImageTagsPost('/api/image/tags', [
            'imageId' => $image->getId(),
            'tags' => mb_strtoupper($tagName, 'UTF-8') . ', ' . $unknownName,
        ]);

        self::assertSame(HttpStatus::OK, $response->getStatus());
        self::assertSame(
            ['applied' => [$tagName], 'unknown' => [$unknownName]],
            json_decode((string)$response->getContent(), true, JSON_THROW_ON_ERROR),
        );
        self::assertSame([$tagName], $this->tagService->getTagsForImage($image->getId()));
        $usage = $this->findUserTagUsage($userId, $tagName);
        self::assertNotNull($usage, 'a newly attached tag should be tracked for the active user');
        self::assertSame(1, (int)$usage['usage_count']);
        self::assertNotNull($usage['last_used_at']);
        $entries = $this->auditLogService->getEntries(1, $loginName)['entries'];
        self::assertCount(1, $entries);
        self::assertSame(AuditActionType::TAG_ADDED->value, $entries[0]['actionType']);
        self::assertSame("image_id={$image->getId()};tag=$tagName", $entries[0]['context']);

        $reattached = $this->simulateImageTagsPost('/api/image/tags', [
            'imageId' => $image->getId(),
            'tags' => $tagName,
        ]);
        self::assertSame(
            ['applied' => [$tagName], 'unknown' => []],
            json_decode((string)$reattached->getContent(), true, JSON_THROW_ON_ERROR),
        );
        self::assertCount(1, $this->auditLogService->getEntries(1, $loginName)['entries']);
        self::assertSame(1, (int)$this->findUserTagUsage($userId, $tagName)['usage_count']);

        $this->simulateImageTagsPost('/api/image/tags', [
            'imageId' => $image->getId(),
            'tags' => 'tagme',
        ]);
        self::assertNull($this->findUserTagUsage($userId, 'tagme'), 'system tagme usage must not be tracked');
    }

    public function testAddActionReturnsBadRequestForInvalidFieldsAndNotFoundForUnknownImage(): void
    {
        $this->loginUser('image-tag-invalid-' . bin2hex(random_bytes(5)), 'Image Tag Invalid');

        self::assertSame(
            HttpStatus::BAD_REQUEST,
            $this->simulateImageTagsPost('/api/image/tags', ['imageId' => 0, 'tags' => 'valid'])->getStatus(),
        );
        self::assertSame(
            HttpStatus::BAD_REQUEST,
            $this->simulateImageTagsPost('/api/image/tags', ['imageId' => 1])->getStatus(),
        );
        self::assertSame(
            HttpStatus::BAD_REQUEST,
            $this->simulateImageTagsPost('/api/image/tags', ['imageId' => 1, 'tags' => ' , '])->getStatus(),
        );
        self::assertSame(
            HttpStatus::BAD_REQUEST,
            $this->simulateImageTagsPost('/api/image/tags', [
                'imageId' => 1,
                'tags' => str_repeat('x', TagInputLimits::MAX_TAG_LIST_LENGTH + 1),
            ])->getStatus(),
        );
        self::assertSame(
            HttpStatus::BAD_REQUEST,
            $this->simulateImageTagsPost('/api/image/tags', [
                'imageId' => 1,
                'tags' => implode(',', array_fill(0, TagInputLimits::MAX_TAG_COUNT + 1, 'tag')),
            ])->getStatus(),
        );
        self::assertSame(
            HttpStatus::NOT_FOUND,
            $this->simulateImageTagsPost('/api/image/tags', ['imageId' => 999999, 'tags' => 'valid'])->getStatus(),
        );
    }

    public function testTagActionsRejectMissingImageId(): void
    {
        $this->loginUser('image-tag-missing-image-id-' . bin2hex(random_bytes(5)), 'Image Tag Missing Image ID');

        $addResponse = $this->simulateImageTagsPost('/api/image/tags', ['tags' => 'valid']);
        self::assertSame(HttpStatus::BAD_REQUEST, $addResponse->getStatus());

        $removeResponse = $this->simulateImageTagsPost('/api/image/tags/remove', ['tag' => 'valid']);
        self::assertSame(HttpStatus::BAD_REQUEST, $removeResponse->getStatus());
    }

    public function testPendingReviewImagesAreForbiddenUnlessTaggedTagmeOrOwned(): void
    {
        $loginName = 'image-tag-pending-' . bin2hex(random_bytes(5));
        $this->loginUser($loginName, 'Image Tag Pending');
        $userId = $this->sessionService->getUserData()->id;
        $otherUserId = $userId === 1 ? 2 : 1;
        $image = ImageTestUtils::createImageEntity(
            $this->imageEntityService,
            userId: $otherUserId,
            status: ImageStatus::PENDING_REVIEW,
        );

        self::assertSame(
            HttpStatus::FORBIDDEN,
            $this->simulateImageTagsPost('/api/image/tags', ['imageId' => $image->getId(), 'tags' => 'tag'])->getStatus(),
        );
        self::assertSame(
            HttpStatus::FORBIDDEN,
            $this->simulateImageTagsPost('/api/image/tags/remove', ['imageId' => $image->getId(), 'tag' => 'tag'])->getStatus(),
        );

        $gameTag = 'game-' . bin2hex(random_bytes(4));
        $this->tagService->createTag($gameTag);
        $this->tagService->attachTagToImage($image->getId(), 'tagme');

        $addResponse = $this->simulateImageTagsPost('/api/image/tags', [
            'imageId' => $image->getId(),
            'tags' => $gameTag,
        ]);
        self::assertSame(HttpStatus::OK, $addResponse->getStatus());
        $tags = $this->tagService->getTagsForImage($image->getId());
        self::assertContains($gameTag, $tags);
        self::assertContains('tagme', $tags);

        $removeResponse = $this->simulateImageTagsPost('/api/image/tags/remove', [
            'imageId' => $image->getId(),
            'tag' => $gameTag,
        ]);
        self::assertSame(HttpStatus::NO_CONTENT, $removeResponse->getStatus());
    }

    public function testUploaderCanAddAndRemoveTagsOnOwnPendingReviewImage(): void
    {
        $this->loginUser('image-tag-owner-' . bin2hex(random_bytes(5)), 'Image Tag Owner');
        $user = $this->sessionService->getUserData();
        self::assertNotNull($user);
        $image = ImageTestUtils::createImageEntity(
            $this->imageEntityService,
            userId: $user->id,
            status: ImageStatus::PENDING_REVIEW,
        );
        $tagName = 'pending-owner-' . bin2hex(random_bytes(4));
        $this->tagService->createTag($tagName);

        $added = $this->simulateImageTagsPost('/api/image/tags', [
            'imageId' => $image->getId(),
            'tags' => $tagName,
        ]);
        self::assertSame(HttpStatus::OK, $added->getStatus());
        self::assertSame([$tagName], $this->tagService->getTagsForImage($image->getId()));

        $removed = $this->simulateImageTagsPost('/api/image/tags/remove', [
            'imageId' => $image->getId(),
            'tag' => $tagName,
        ]);
        self::assertSame(HttpStatus::NO_CONTENT, $removed->getStatus());
        self::assertSame([], $this->tagService->getTagsForImage($image->getId()));
    }

    public function testRemoveActionEnforcesTagmeMinimumAuditsRemovalAndMapsMissingRows(): void
    {
        self::assertSame(
            HttpStatus::UNAUTHORIZED,
            $this->simulateImageTagsPost('/api/image/tags/remove', ['imageId' => 1, 'tag' => 'tagme'])->getStatus(),
        );

        $loginName = 'image-tag-remove-' . bin2hex(random_bytes(5));
        $this->loginUser($loginName, 'Image Tag Remove');
        $userId = $this->sessionService->getUserData()->id;

        $image = ImageTestUtils::createImageEntity($this->imageEntityService, $userId);
        $ordinaryTag = 'remove-' . bin2hex(random_bytes(4));
        $this->tagService->createTag($ordinaryTag);
        $this->simulateImageTagsPost('/api/image/tags', [
            'imageId' => $image->getId(),
            'tags' => $ordinaryTag,
        ]);
        $usageBeforeRemoval = $this->findUserTagUsage($userId, $ordinaryTag);
        self::assertNotNull($usageBeforeRemoval);
        self::assertSame(1, (int)$usageBeforeRemoval['usage_count']);
        $removed = $this->simulateImageTagsPost('/api/image/tags/remove', [
            'imageId' => $image->getId(),
            'tag' => ' ' . mb_strtoupper($ordinaryTag, 'UTF-8') . ' ',
        ]);
        self::assertSame(HttpStatus::NO_CONTENT, $removed->getStatus());
        self::assertNull($removed->getContent());
        self::assertSame([], $this->tagService->getTagsForImage($image->getId()));
        $usageAfterRemoval = $this->findUserTagUsage($userId, $ordinaryTag);
        self::assertNotNull($usageAfterRemoval);
        self::assertSame(0, (int)$usageAfterRemoval['usage_count']);
        self::assertSame($usageBeforeRemoval['last_used_at'], $usageAfterRemoval['last_used_at']);

        $entries = $this->auditLogService->getEntries(1, $loginName)['entries'];
        self::assertCount(2, $entries);
        $removedEntry = array_values(array_filter(
            $entries,
            static fn(array $entry): bool => $entry['actionType'] === AuditActionType::TAG_REMOVED->value,
        ))[0] ?? null;
        self::assertNotNull($removedEntry);
        self::assertSame("image_id={$image->getId()};tag=$ordinaryTag", $removedEntry['context']);

        self::assertSame(
            HttpStatus::NOT_FOUND,
            $this->simulateImageTagsPost('/api/image/tags/remove', [
                'imageId' => $image->getId(),
                'tag' => 'missing-' . bin2hex(random_bytes(4)),
            ])->getStatus(),
        );
        self::assertSame(
            HttpStatus::NOT_FOUND,
            $this->simulateImageTagsPost('/api/image/tags/remove', [
                'imageId' => 999999,
                'tag' => 'tagme',
            ])->getStatus(),
        );
        self::assertSame(
            HttpStatus::BAD_REQUEST,
            $this->simulateImageTagsPost('/api/image/tags/remove', ['imageId' => 0, 'tag' => 'tagme'])->getStatus(),
        );
        self::assertSame(
            HttpStatus::BAD_REQUEST,
            $this->simulateImageTagsPost('/api/image/tags/remove', ['imageId' => $image->getId()])->getStatus(),
        );
        self::assertSame(
            HttpStatus::BAD_REQUEST,
            $this->simulateImageTagsPost('/api/image/tags/remove', [
                'imageId' => $image->getId(),
                'tag' => str_repeat('x', TagInputLimits::MAX_TAG_LENGTH + 1),
            ])->getStatus(),
        );

        $blockedImage = ImageTestUtils::createImageEntity($this->imageEntityService, $userId);
        $this->tagService->attachTagToImage($blockedImage->getId(), 'tagme');
        $blocked = $this->simulateImageTagsPost('/api/image/tags/remove', [
            'imageId' => $blockedImage->getId(),
            'tag' => 'TAGME',
        ]);
        self::assertSame(HttpStatus::CONFLICT, $blocked->getStatus());
        self::assertContains('tagme', $this->tagService->getTagsForImage($blockedImage->getId()));

        $allowedImage = ImageTestUtils::createImageEntity($this->imageEntityService, $userId);
        $this->tagService->attachTagToImage($allowedImage->getId(), 'tagme');
        for ($index = 0; $index < 10; $index++) {
            $this->tagService->attachTagToImage($allowedImage->getId(), 'other-' . $index . '-' . bin2hex(random_bytes(2)));
        }
        self::assertSame(11, $this->tagService->countTagsForImage($allowedImage->getId()));
        $allowed = $this->simulateImageTagsPost('/api/image/tags/remove', [
            'imageId' => $allowedImage->getId(),
            'tag' => 'tagme',
        ]);
        self::assertSame(HttpStatus::NO_CONTENT, $allowed->getStatus());
        self::assertSame(10, $this->tagService->countTagsForImage($allowedImage->getId()));
        self::assertNull($this->findUserTagUsage($userId, 'tagme'));
    }

    /** @return array{usage_count: int|string, last_used_at: string|null}|null */
    private function findUserTagUsage(int $userId, string $tagName): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT user_usage.usage_count, user_usage.last_used_at '
            . 'FROM user_tag_usage AS user_usage '
            . 'INNER JOIN tag ON tag.id = user_usage.tag_id '
            . 'WHERE user_usage.user_id = :user_id AND tag.name = :tag_name COLLATE NOCASE'
        );
        $statement->execute(['user_id' => $userId, 'tag_name' => $tagName]);
        $usage = $statement->fetch(PDO::FETCH_ASSOC);
        return is_array($usage) ? $usage : null;
    }

    private function simulateImageTagsPost(string $path, array $postParams): Response
    {
        return $this->simulateRequest(new Request(
            url: new Url(protocol: 'https', host: 'localhost', path: $path),
            method: HttpMethod::POST,
            postParams: $postParams,
        ));
    }
}
