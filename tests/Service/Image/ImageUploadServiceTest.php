<?php
declare(strict_types=1);

namespace SmolCms\Test\Service\Image;

use GdImage;
use PDO;
use SmolCms\Data\Constant\ImageStatus;
use SmolCms\Data\Image\ImageType;
use SmolCms\Data\Persistence\ImageEntity;
use SmolCms\Exception\DuplicateImageException;
use SmolCms\Exception\InvalidStateException;
use SmolCms\Service\Core\Session\SessionService;
use SmolCms\Service\DB\ImageEntityService;
use SmolCms\Service\Image\ImageUploadService;
use SmolCms\TestUtils\Attributes\Autowire;
use SmolCms\TestUtils\FunctionalTestCase;

class ImageUploadServiceTest extends FunctionalTestCase
{
    #[Autowire]
    private ImageUploadService $imageUploadService;
    #[Autowire]
    private ImageEntityService $imageEntityService;
    #[Autowire]
    private SessionService $sessionService;
    #[Autowire]
    private PDO $pdo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->pdo->exec('DELETE FROM image_tag; DELETE FROM image;');
        $this->loginUser('image-uploader', 'Image Uploader');
        $this->cleanImageStorage();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->cleanImageStorage();
    }

    private static function cleanImageStorage(): void
    {
        $dir = IMAGE_STORAGE_DIR;
        if (!is_dir($dir)) {
            return;
        }
        $entries = scandir($dir);
        if ($entries === false) {
            return;
        }
        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $entryPath = $dir . '/' . $entry;
            if (is_dir($entryPath)) {
                self::rmDirRecursive($entryPath);
            } else {
                unlink($entryPath);
            }
        }
    }

    private static function rmDirRecursive(string $path): void
    {
        $items = scandir($path);
        if ($items === false) {
            return;
        }
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $itemPath = $path . '/' . $item;
            if (is_dir($itemPath)) {
                self::rmDirRecursive($itemPath);
            } else {
                unlink($itemPath);
            }
        }
        rmdir($path);
    }

    public function testUpload_storesOriginalBytesAndCreatesPendingRecord(): void
    {
        $imageData = file_get_contents(ROOT_DIR . '/tests/TestUtils/TestFiles/image.png');

        $entity = $this->imageUploadService->upload($imageData, ImageType::PNG);

        self::assertNotNull($entity->getId());
        self::assertSame(ImageStatus::PENDING, $entity->getStatus());
        self::assertSame('png', $entity->getOriginalExtension());
        self::assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $entity->getChecksum());
        self::assertSame('png', $entity->getFormat());
        self::assertNull($entity->getThumbFilename());
        self::assertNull($entity->getSampleFilename());
        self::assertNull($entity->getDownloadFilename());
        [$expectedWidth, $expectedHeight] = getimagesize(ROOT_DIR . '/tests/TestUtils/TestFiles/image.png');
        self::assertSame($expectedWidth, $entity->getWidth());
        self::assertSame($expectedHeight, $entity->getHeight());

        // Stored originals are flat: <IMAGE_STORAGE_DIR>/<checksum>.png
        $filePath = IMAGE_STORAGE_DIR . '/' . $entity->getChecksum() . '.png';
        self::assertFileExists($filePath);
        $storedContent = file_get_contents($filePath);
        self::assertSame($imageData, $storedContent);
        self::assertSame(hash('sha256', $imageData), $entity->getChecksum());
        self::assertSame(strlen($imageData), $entity->getFileSize());
        self::assertInstanceOf(GdImage::class, imagecreatefromstring($storedContent));
    }

    public function testUpload_assignsTheLoggedInUser(): void
    {
        $userData = $this->sessionService->getUserData();
        $imageData = file_get_contents(ROOT_DIR . '/tests/TestUtils/TestFiles/image.jpg');

        $entity = $this->imageUploadService->upload($imageData, ImageType::JPEG);

        self::assertSame($userData->id, $entity->getUserId());
    }

    public function testUpload_withoutAuthenticatedUser_throwsException(): void
    {
        $this->sessionService->destroySession();
        $imageData = file_get_contents(ROOT_DIR . '/tests/TestUtils/TestFiles/image.png');

        $this->expectException(InvalidStateException::class);
        $this->expectExceptionMessageIsOrContains('Cannot upload an image without an authenticated user.');

        $this->imageUploadService->upload($imageData, ImageType::PNG);
    }

    public function testUpload_roundTripsThroughTheDatabase(): void
    {
        $imageData = file_get_contents(ROOT_DIR . '/tests/TestUtils/TestFiles/image.png');

        $entity = $this->imageUploadService->upload($imageData, ImageType::PNG);

        $reloaded = $this->imageEntityService->findOneById($entity->getId());
        self::assertNotNull($reloaded);
        self::assertSame($entity->getChecksum(), $reloaded->getChecksum());
        self::assertSame($entity->getUserId(), $reloaded->getUserId());
        self::assertSame(ImageStatus::PENDING, $reloaded->getStatus());
        self::assertSame(
            $entity->getUploadDate()->format('Y-m-d H:i:s'),
            $reloaded->getUploadDate()->format('Y-m-d H:i:s')
        );
        $pending = $this->imageEntityService->findByStatus(ImageStatus::PENDING);
        self::assertContains($entity->getId(), array_map(
            static fn(ImageEntity $pendingEntity) => $pendingEntity->getId(),
            $pending
        ));
    }

    public function testUpload_sameImageTwice_throwsDuplicateImageExceptionWithoutStoringIt(): void
    {
        $imageData = file_get_contents(ROOT_DIR . '/tests/TestUtils/TestFiles/image.png');
        $first = $this->imageUploadService->upload($imageData, ImageType::PNG);

        $this->expectException(DuplicateImageException::class);
        $this->expectExceptionMessageIsOrContains('already been uploaded');
        try {
            $this->imageUploadService->upload($imageData, ImageType::PNG);
        } finally {
            self::assertCount(1, $this->imageEntityService->findByStatus(ImageStatus::PENDING));
            self::assertSame(
                hash('sha256', $imageData),
                $this->imageEntityService->findOneById($first->getId())?->getChecksum()
            );
            self::assertFileExists(IMAGE_STORAGE_DIR . '/' . $first->getChecksum() . '.png');
        }
    }

    public function testHasDuplicateChecksum_isTrueForAnyExistingImageWithTheSameBytes(): void
    {
        $imageData = file_get_contents(ROOT_DIR . '/tests/TestUtils/TestFiles/image.jpg');
        $checksum = hash('sha256', $imageData);

        self::assertFalse($this->imageUploadService->hasDuplicateChecksum($checksum));
        $this->imageUploadService->upload($imageData, ImageType::JPEG);
        self::assertTrue($this->imageUploadService->hasDuplicateChecksum($checksum));
    }
}
