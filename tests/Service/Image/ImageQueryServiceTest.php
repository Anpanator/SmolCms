<?php

declare(strict_types=1);

namespace SmolCms\Test\Service\Image;

use DateTime;
use SmolCms\Data\Constant\ImageStatus;
use SmolCms\Data\Constant\RouteEnum;
use SmolCms\Data\Persistence\ImageEntity;
use SmolCms\Data\Persistence\UserEntity;
use SmolCms\Service\DB\ImageEntityService;
use SmolCms\Service\DB\UserService;
use SmolCms\Service\Image\ImagePathLayout;
use SmolCms\Service\Image\ImageQueryService;
use SmolCms\Service\Tag\TagService;
use SmolCms\TestUtils\Attributes\Autowire;
use SmolCms\TestUtils\FunctionalTestCase;
use SmolCms\TestUtils\ImageTestUtils;

class ImageQueryServiceTest extends FunctionalTestCase
{
    #[Autowire]
    private ImageQueryService $imageQueryService;

    #[Autowire]
    private ImageEntityService $imageEntityService;

    #[Autowire]
    private UserService $userService;

    #[Autowire]
    private TagService $tagService;

    public function testImagePageMapsImageSummariesAndPageTagCloud(): void
    {
        $tagName = 'query-page-' . bin2hex(random_bytes(4));
        $older = ImageTestUtils::createImageEntity(
            $this->imageEntityService,
            userId: 1,
            status: ImageStatus::ACCEPTED,
            uploadDate: new DateTime('2026-10-01 10:00:00'),
        );
        $newerThumb = $this->storedFileName('newer-thumb');
        $newer = ImageTestUtils::createImageEntity(
            $this->imageEntityService,
            userId: 1,
            status: ImageStatus::ACCEPTED,
            uploadDate: new DateTime('2026-10-01 11:00:00'),
            thumbFilename: $newerThumb,
        );
        $pending = ImageTestUtils::createImageEntity(
            $this->imageEntityService,
            userId: 1,
            status: ImageStatus::PENDING_REVIEW,
            uploadDate: new DateTime('2026-10-01 12:00:00'),
        );
        foreach ([$older, $newer, $pending] as $image) {
            $this->tagService->attachTagToImage($image->getId(), $tagName);
        }

        $result = $this->imageQueryService->getImagePage(1, [$tagName, strtoupper($tagName), '']);

        self::assertSame(
            [
                ['id' => $newer->getId(), 'thumbSrc' => '/public/img/thumb/' . ImagePathLayout::relativePathForFileName($newerThumb), 'status' => 'accepted'],
                ['id' => $older->getId(), 'thumbSrc' => null, 'status' => 'accepted'],
            ],
            $result['images'],
        );
        self::assertSame(1, $result['page']);
        self::assertSame(25, $result['perPage']);
        self::assertSame(2, $result['total']);
        self::assertSame([['name' => $tagName, 'imageCount' => 2]], $result['tags']);
    }

    public function testImageDetailMapsUploaderVariantUrlsAndTags(): void
    {
        $user = $this->createUser('image-detail-' . bin2hex(random_bytes(4)), 'Image Detail Uploader');
        $thumbFilename = $this->storedFileName('detail-thumb');
        $sampleFilename = $this->storedFileName('detail-sample');
        $downloadFilename = $this->storedFileName('detail-download');
        $image = ImageTestUtils::createImageEntity(
            $this->imageEntityService,
            userId: $user->getId(),
            status: ImageStatus::ACCEPTED,
            uploadDate: new DateTime('2026-10-03 14:15:16'),
            thumbFilename: $thumbFilename,
            sampleFilename: $sampleFilename,
            downloadFilename: $downloadFilename,
        );
        $tagName = 'image-detail-tag-' . bin2hex(random_bytes(3));
        $this->tagService->attachTagToImage($image->getId(), $tagName);

        self::assertSame(
            [
                'status' => 'accepted',
                'id' => $image->getId(),
                'uploadDate' => '2026-10-03 14:15:16',
                'uploaderId' => $user->getId(),
                'uploader' => 'Image Detail Uploader',
                'width' => 100,
                'height' => 50,
                'format' => 'jpeg',
                'fileSize' => 1234,
                'sampleSrc' => RouteEnum::API_IMAGE_SAMPLE->value . '?name=' . rawurlencode($sampleFilename),
                'downloadSrc' => RouteEnum::API_IMAGE_DOWNLOAD->value . '?name=' . rawurlencode($downloadFilename),
                'tags' => [$tagName],
            ],
            $this->imageQueryService->getImageDetail($image->getId()),
        );
        self::assertSame(
            '/public/img/thumb/' . ImagePathLayout::relativePathForFileName($thumbFilename),
            $this->imageQueryService->getThumbSrc($image),
        );
        self::assertNull($this->imageQueryService->getImageDetail(PHP_INT_MAX));
        self::assertNull($this->imageQueryService->getThumbSrc($this->imageWithoutThumb()));
    }

    public function testImageDetailUsesNullUploaderFieldsWhenUploaderIsMissing(): void
    {
        $image = ImageTestUtils::createImageEntity(
            $this->imageEntityService,
            userId: PHP_INT_MAX,
            status: ImageStatus::ACCEPTED,
        );

        $detail = $this->imageQueryService->getImageDetail($image->getId());

        self::assertNotNull($detail);
        self::assertNull($detail['uploaderId']);
        self::assertNull($detail['uploader']);
    }

    private function createUser(string $loginName, string $displayName): UserEntity
    {
        $user = new UserEntity(
            id: null,
            loginName: $loginName,
            password: 'hashed-password',
            displayName: $displayName,
            state: 'active',
            registerDate: new DateTime('2026-10-20 12:00:00'),
            lastLoginDate: null,
            accessLevel: \SmolCms\Data\Constant\AccessLevel::NOVICE,
        );
        $this->userService->saveAsNew($user);
        return $user;
    }

    private function storedFileName(string $label): string
    {
        return bin2hex(random_bytes(16)) . '-' . $label . '.jpeg';
    }

    private function imageWithoutThumb(): ImageEntity
    {
        return new ImageEntity(
            id: 0,
            userId: 1,
            uploadDate: new DateTime('2026-10-03 14:15:16'),
            status: ImageStatus::ACCEPTED,
            checksum: ImageTestUtils::randomChecksum(),
            originalExtension: 'jpeg',
        );
    }
}
