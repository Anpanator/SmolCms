<?php

declare(strict_types=1);

namespace SmolCms\Test\Service\Image;

use DateTime;
use GdImage;
use InvalidArgumentException;
use PDO;
use SmolCms\Data\Business\Service;
use SmolCms\Data\Constant\AccessLevel;
use SmolCms\Data\Constant\ImageStatus;
use SmolCms\Data\Image\ImageType;
use SmolCms\Data\Persistence\ImageEntity;
use SmolCms\Data\Persistence\UserEntity;
use SmolCms\Service\DB\ImageEntityService;
use SmolCms\Service\DB\UserService;
use SmolCms\Service\File\FileSystemAccessService;
use SmolCms\Service\Image\ImageDimensions;
use SmolCms\Service\Image\ImagePathLayout;
use SmolCms\Service\Image\ImageProcessingService;
use SmolCms\Service\Image\ImageStorageService;
use SmolCms\TestUtils\Attributes\Autowire;
use SmolCms\TestUtils\FunctionalTestCase;

class ImageProcessingServiceTest extends FunctionalTestCase
{
    private const string TEST_BASE_DIR = '/private/upload/test-image-processing';

    #[Autowire]
    private ImageProcessingService $testee;
    #[Autowire]
    private ImageEntityService $imageEntityService;
    #[Autowire]
    private UserService $userService;
    #[Autowire]
    private ImageStorageService $originalStorage;
    #[Autowire]
    private PDO $pdo;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::registerTestServices(
            new Service(
                identifier: ImageStorageService::class . '.thumb',
                class: ImageStorageService::class,
                parameters: [ROOT_DIR . self::TEST_BASE_DIR . '/thumb', FileSystemAccessService::class]
            ),
            new Service(
                identifier: ImageStorageService::class . '.sample',
                class: ImageStorageService::class,
                parameters: [ROOT_DIR . self::TEST_BASE_DIR . '/sample', FileSystemAccessService::class]
            ),
            new Service(
                identifier: ImageStorageService::class . '.download',
                class: ImageStorageService::class,
                parameters: [ROOT_DIR . self::TEST_BASE_DIR . '/download', FileSystemAccessService::class]
            ),
        );
    }

    protected function setUp(): void
    {
        parent::setUp();
        // The in-memory DB is shared across classes; rows from other tests (their files
        // are already cleaned up) would otherwise occupy batch slots and break assertions.
        $this->pdo->exec('DELETE FROM image');
        self::rmDirContents(IMAGE_STORAGE_DIR);
        self::rmDirRecursive(ROOT_DIR . self::TEST_BASE_DIR);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        self::rmDirContents(IMAGE_STORAGE_DIR);
        self::rmDirRecursive(ROOT_DIR . self::TEST_BASE_DIR);
    }

    private static function rmDirContents(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }
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
    }

    private static function rmDirRecursive(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }
        self::rmDirContents($path);
        rmdir($path);
    }

    private function createUser(AccessLevel $accessLevel): UserEntity
    {
        $user = new UserEntity(
            id: null,
            loginName: 'img-proc-' . bin2hex(random_bytes(8)),
            password: 'untestable',
            displayName: 'Image Processing Test User',
            state: 'active',
            registerDate: new DateTime(),
            lastLoginDate: null,
            accessLevel: $accessLevel,
        );
        $this->userService->saveAsNew($user);
        return $user;
    }

    private function createPendingImage(
        GdImage $image,
        ImageType $type,
        int $userId,
        DateTime $uploadDate = new DateTime(),
    ): ImageEntity {
        $content = self::encodeImage($image, $type);
        $stored = $this->originalStorage->storeOriginal($content, $type, $image);
        $entity = new ImageEntity(
            id: null,
            userId: $userId,
            uploadDate: $uploadDate,
            status: ImageStatus::PENDING,
            checksum: $stored->getChecksum(),
            originalExtension: $type->getFileExtension(),
        );
        $this->imageEntityService->saveAsNew($entity);
        return $entity;
    }

    private function createPendingImageFromContent(
        string    $content,
        ImageType $type,
        int       $userId,
        DateTime  $uploadDate = new DateTime(),
    ): ImageEntity
    {
        $checksum = hash('sha256', $content);
        $this->originalStorage->storeBytes($content, ImagePathLayout::originalRelativePath($checksum, $type->getFileExtension()));
        $entity = new ImageEntity(
            id: null,
            userId: $userId,
            uploadDate: $uploadDate,
            status: ImageStatus::PENDING,
            checksum: $checksum,
            originalExtension: $type->getFileExtension(),
        );
        $this->imageEntityService->saveAsNew($entity);
        return $entity;
    }

    private static function createTestImage(int $width, int $height): GdImage
    {
        $image = imagecreatetruecolor($width, $height);
        // Fill with varying colors so lossless comparisons have something to bite on.
        imagefill($image, 0, 0, 0xFF0000);
        imagefilledrectangle($image, 0, 0, intdiv($width, 2), $height, 0x00FF00);
        imagefilledrectangle($image, intdiv($width, 4), intdiv($height, 4), intdiv($width, 2), intdiv($height, 2), 0x0000FF);
        return $image;
    }

    private static function variantPath(string $baseDir, string $filename): string
    {
        $checksum = pathinfo($filename, PATHINFO_FILENAME);
        return "$baseDir/" . substr($checksum, 0, 2) . "/$filename";
    }

    private function originalPath(ImageEntity $entity): string
    {
        return $this->originalStorage->getStoragePath() . '/'
            . ImagePathLayout::originalRelativePath($entity->getChecksum(), $entity->getOriginalExtension());
    }

    private static function assertChecksumFilename(string $baseDir, string $filename): string
    {
        $path = self::variantPath($baseDir, $filename);
        self::assertFileExists($path);
        $content = file_get_contents($path);
        self::assertSame(pathinfo($filename, PATHINFO_FILENAME), hash('sha256', $content));
        return $content;
    }

    public function testProcessBatch_pngImage_generatesVariantsAndAcceptsForWizard(): void
    {
        $wizard = $this->createUser(AccessLevel::WIZARD);
        $entity = $this->createPendingImage(self::createTestImage(640, 320), ImageType::PNG, $wizard->getId());

        $processed = $this->testee->processBatch(10);

        self::assertSame(1, $processed);
        self::assertFileDoesNotExist($this->originalPath($entity));

        $reloaded = $this->imageEntityService->findOneById($entity->getId());
        self::assertNotNull($reloaded);
        self::assertSame(ImageStatus::ACCEPTED, $reloaded->getStatus());

        // Thumbnail: AVIF (magic "ftyp" at offset 4), resized to fit 512x512, aspect kept
        $thumbContent = self::assertChecksumFilename(ROOT_DIR . self::TEST_BASE_DIR . '/thumb', $reloaded->getThumbFilename());
        self::assertMatchesRegularExpression('/^[0-9a-f]{64}\.avif$/', $reloaded->getThumbFilename());
        self::assertSame('ftyp', substr($thumbContent, 4, 4));
        $thumbImage = imagecreatefromstring($thumbContent);
        self::assertSame(512, imagesx($thumbImage));
        self::assertSame(256, imagesy($thumbImage));

        // Sample: WebP (RIFF/WEBP magic), fits 2048x2048, never upscaled
        $sampleContent = self::assertChecksumFilename(ROOT_DIR . self::TEST_BASE_DIR . '/sample', $reloaded->getSampleFilename());
        self::assertMatchesRegularExpression('/^[0-9a-f]{64}\.webp$/', $reloaded->getSampleFilename());
        self::assertSame('RIFF', substr($sampleContent, 0, 4));
        self::assertSame('WEBP', substr($sampleContent, 8, 4));
        $sampleImage = imagecreatefromstring($sampleContent);
        self::assertSame(640, imagesx($sampleImage));
        self::assertSame(320, imagesy($sampleImage));

        // Download: PNG source is transcoded to lossless WebP; metadata mirrors the download
        $downloadContent = self::assertChecksumFilename(ROOT_DIR . self::TEST_BASE_DIR . '/download', $reloaded->getDownloadFilename());
        self::assertMatchesRegularExpression('/^[0-9a-f]{64}\.webp$/', $reloaded->getDownloadFilename());
        self::assertSame('RIFF', substr($downloadContent, 0, 4));
        self::assertSame('WEBP', substr($downloadContent, 8, 4));
        self::assertSame(640, $reloaded->getWidth());
        self::assertSame(320, $reloaded->getHeight());
        self::assertSame('webp', $reloaded->getFormat());
        self::assertSame(strlen($downloadContent), $reloaded->getFileSize());
    }

    public function testProcessBatch_pngDownload_isPixelLossless(): void
    {
        $wizard = $this->createUser(AccessLevel::WIZARD);
        $original = self::createTestImage(300, 200);
        $entity = $this->createPendingImage($original, ImageType::PNG, $wizard->getId());

        $this->testee->processBatch(10);

        $reloaded = $this->imageEntityService->findOneById($entity->getId());
        $downloadImage = imagecreatefromstring(
            file_get_contents(self::variantPath(ROOT_DIR . self::TEST_BASE_DIR . '/download', $reloaded->getDownloadFilename()))
        );
        self::assertInstanceOf(GdImage::class, $downloadImage);
        foreach ([[0, 0], [299, 199], [150, 100], [0, 199], [299, 0], [75, 50]] as [$x, $y]) {
            self::assertSame(
                imagecolorat($original, $x, $y),
                imagecolorat($downloadImage, $x, $y),
                "Pixel ($x, $y) differs after lossless WebP transcoding"
            );
        }
    }

    public function testProcessBatch_jpegImage_reencodesAtMaximumQualityAndSetsPendingReviewForNovice(): void
    {
        $novice = $this->createUser(AccessLevel::NOVICE);
        $injectedPayload = "\nSMOLCMS-UNSAFE-TRAILING-PAYLOAD";
        $originalContent = self::jpegWithExifApp1Segment(
                self::encodeImage(self::createTestImage(300, 200), ImageType::JPEG, 75)
            ) . $injectedPayload;
        $entity = $this->createPendingImageFromContent($originalContent, ImageType::JPEG, $novice->getId());

        $processed = $this->testee->processBatch(10);

        self::assertSame(1, $processed);
        $reloaded = $this->imageEntityService->findOneById($entity->getId());
        self::assertNotNull($reloaded);

        self::assertNotNull($reloaded->getThumbFilename());
        self::assertNotNull($reloaded->getSampleFilename());

        // Download: JPEG is rewritten at maximum quality; EXIF metadata is stripped.
        $downloadContent = self::assertChecksumFilename(ROOT_DIR . self::TEST_BASE_DIR . '/download', $reloaded->getDownloadFilename());
        self::assertMatchesRegularExpression('/^[0-9a-f]{64}\.jpeg$/', $reloaded->getDownloadFilename());
        self::assertNotSame($originalContent, $downloadContent);
        self::assertStringNotContainsString("Exif\x00\x00", $downloadContent);
        self::assertStringNotContainsString($injectedPayload, $downloadContent);
        $decodedOriginal = imagecreatefromstring($originalContent);
        self::assertInstanceOf(GdImage::class, $decodedOriginal);
        self::assertSame(self::encodeImage($decodedOriginal, ImageType::JPEG, 100), $downloadContent);
        self::assertSame(300, $reloaded->getWidth());
        self::assertSame(200, $reloaded->getHeight());
        self::assertSame('jpeg', $reloaded->getFormat());
        self::assertSame(strlen($downloadContent), $reloaded->getFileSize());

        // NOVICE uploader does not skip the moderation queue
        self::assertSame(ImageStatus::PENDING_REVIEW, $reloaded->getStatus());
        self::assertFileDoesNotExist($this->originalPath($entity));
    }

    public function testProcessBatch_webpImage_reencodesLosslessly(): void
    {
        $wizard = $this->createUser(AccessLevel::WIZARD);
        $originalContent = self::encodeImage(self::createTestImage(300, 200), ImageType::WEBP, 66);
        $entity = $this->createPendingImageFromContent($originalContent, ImageType::WEBP, $wizard->getId());

        self::assertSame(1, $this->testee->processBatch(10));

        $reloaded = $this->imageEntityService->findOneById($entity->getId());
        self::assertNotNull($reloaded);
        $downloadContent = self::assertChecksumFilename(
            ROOT_DIR . self::TEST_BASE_DIR . '/download',
            $reloaded->getDownloadFilename()
        );
        self::assertMatchesRegularExpression('/^[0-9a-f]{64}\.webp$/', $reloaded->getDownloadFilename());
        self::assertNotSame($originalContent, $downloadContent);

        $decodedOriginal = imagecreatefromstring($originalContent);
        self::assertInstanceOf(GdImage::class, $decodedOriginal);
        self::assertSame(
            self::encodeImage($decodedOriginal, ImageType::WEBP, IMG_WEBP_LOSSLESS),
            $downloadContent
        );
    }

    public function testProcessBatch_avifImage_reencodesAtQuality75(): void
    {
        $wizard = $this->createUser(AccessLevel::WIZARD);
        $originalContent = self::encodeImage(self::createTestImage(300, 200), ImageType::AVIF, 100);
        $entity = $this->createPendingImageFromContent($originalContent, ImageType::AVIF, $wizard->getId());

        self::assertSame(1, $this->testee->processBatch(10));

        $reloaded = $this->imageEntityService->findOneById($entity->getId());
        self::assertNotNull($reloaded);
        $downloadContent = self::assertChecksumFilename(
            ROOT_DIR . self::TEST_BASE_DIR . '/download',
            $reloaded->getDownloadFilename()
        );
        self::assertMatchesRegularExpression('/^[0-9a-f]{64}\.avif$/', $reloaded->getDownloadFilename());
        self::assertNotSame($originalContent, $downloadContent);

        $decodedOriginal = imagecreatefromstring($originalContent);
        self::assertInstanceOf(GdImage::class, $decodedOriginal);
        self::assertSame(self::encodeImage($decodedOriginal, ImageType::AVIF, 75), $downloadContent);
    }

    public function testProcessBatch_duplicatePendingImagesDeletesSharedOriginalAfterBothAreProcessed(): void
    {
        $wizard = $this->createUser(AccessLevel::WIZARD);
        $image = self::createTestImage(24, 16);
        $first = $this->createPendingImage($image, ImageType::PNG, $wizard->getId());
        $second = $this->createPendingImage($image, ImageType::PNG, $wizard->getId());

        self::assertSame($first->getChecksum(), $second->getChecksum());
        self::assertFileExists($this->originalPath($first));

        self::assertSame(2, $this->testee->processBatch(10));

        self::assertFileDoesNotExist($this->originalPath($first));
        self::assertSame(ImageStatus::ACCEPTED, $this->imageEntityService->findOneById($first->getId())->getStatus());
        self::assertSame(ImageStatus::ACCEPTED, $this->imageEntityService->findOneById($second->getId())->getStatus());
    }

    public function testProcessBatch_orphanedOriginalLogsWarningAndDeletesIt(): void
    {
        $content = self::encodeImage(self::createTestImage(24, 16), ImageType::PNG);
        $checksum = hash('sha256', $content);
        $fileName = ImagePathLayout::fileName($checksum, ImageType::PNG->getFileExtension());
        $originalPath = $this->originalStorage->getStoragePath() . '/' . $fileName;
        $this->originalStorage->storeBytes($content, $fileName);
        $logBefore = file_get_contents(ERROR_LOG_FILE_PATH);
        self::assertIsString($logBefore);

        self::assertSame(0, $this->testee->processBatch(10));

        self::assertFileDoesNotExist($originalPath);
        $logAfter = file_get_contents(ERROR_LOG_FILE_PATH);
        self::assertIsString($logAfter);
        $newLogEntries = substr($logAfter, strlen($logBefore));
        self::assertStringContainsString('[WARNING]', $newLogEntries);
        self::assertStringContainsString('no matching database image', $newLogEntries);
        self::assertStringContainsString($fileName, $newLogEntries);
    }

    public function testProcessBatch_reprocessingIsIdempotentAndRetriesCompletedOriginalCleanup(): void
    {
        $wizard = $this->createUser(AccessLevel::WIZARD);
        $entity = $this->createPendingImage(self::createTestImage(48, 32), ImageType::PNG, $wizard->getId());
        $originalPath = $this->originalPath($entity);
        $originalContent = file_get_contents($originalPath);
        self::assertIsString($originalContent);

        self::assertSame(1, $this->testee->processBatch(10));
        $firstResult = $this->imageEntityService->findOneById($entity->getId());
        self::assertNotNull($firstResult);
        $firstFilenames = [
            $firstResult->getThumbFilename(),
            $firstResult->getSampleFilename(),
            $firstResult->getDownloadFilename(),
        ];

        // Simulate an interrupted, partial database state after variants were written.
        $this->originalStorage->storeBytes(
            $originalContent,
            ImagePathLayout::originalRelativePath($entity->getChecksum(), $entity->getOriginalExtension())
        );
        $firstResult->setStatus(ImageStatus::PENDING);
        $this->imageEntityService->saveOrUpdate($firstResult);

        self::assertSame(1, $this->testee->processBatch(10));
        $secondResult = $this->imageEntityService->findOneById($entity->getId());
        self::assertNotNull($secondResult);
        self::assertSame($firstFilenames, [
            $secondResult->getThumbFilename(),
            $secondResult->getSampleFilename(),
            $secondResult->getDownloadFilename(),
        ]);
        self::assertFileDoesNotExist($originalPath);

        // Simulate an interruption after the entity update but before original deletion.
        $this->originalStorage->storeBytes(
            $originalContent,
            ImagePathLayout::originalRelativePath($entity->getChecksum(), $entity->getOriginalExtension())
        );
        self::assertFileExists($originalPath);

        $logBefore = file_get_contents(ERROR_LOG_FILE_PATH);
        self::assertIsString($logBefore);
        self::assertSame(0, $this->testee->processBatch(10));
        self::assertFileDoesNotExist($originalPath);
        $logAfter = file_get_contents(ERROR_LOG_FILE_PATH);
        self::assertIsString($logAfter);
        $newLogEntries = substr($logAfter, strlen($logBefore));
        self::assertStringContainsString('[WARNING]', $newLogEntries);
        self::assertStringContainsString('already fully processed', $newLogEntries);
    }

    public function testProcessBatch_corruptOriginal_staysPendingAndBatchContinues(): void
    {
        $wizard = $this->createUser(AccessLevel::WIZARD);
        $corruptContent = 'this is not decodable image data';
        $corruptChecksum = hash('sha256', $corruptContent);
        $this->originalStorage->storeBytes($corruptContent, ImagePathLayout::originalRelativePath($corruptChecksum, 'png'));
        $corruptEntity = new ImageEntity(
            id: null,
            userId: $wizard->getId(),
            uploadDate: new DateTime('2026-01-01 00:00:00'),
            status: ImageStatus::PENDING,
            checksum: $corruptChecksum,
            originalExtension: 'png',
        );
        $this->imageEntityService->saveAsNew($corruptEntity);
        $goodEntity = $this->createPendingImage(
            self::createTestImage(10, 10),
            ImageType::PNG,
            $wizard->getId(),
            new DateTime('2026-01-02 00:00:00')
        );

        // The failed decode raises a PHP warning deep inside GD; processBatch catches it.
        $processed = $this->testee->processBatch(10);

        self::assertSame(1, $processed);
        $corruptReloaded = $this->imageEntityService->findOneById($corruptEntity->getId());
        self::assertNotNull($corruptReloaded);
        self::assertSame(ImageStatus::PENDING, $corruptReloaded->getStatus());
        self::assertNull($corruptReloaded->getThumbFilename());
        self::assertNull($corruptReloaded->getSampleFilename());
        self::assertNull($corruptReloaded->getDownloadFilename());
        self::assertFileExists($this->originalPath($corruptEntity));
        $goodReloaded = $this->imageEntityService->findOneById($goodEntity->getId());
        self::assertNotNull($goodReloaded);
        self::assertSame(ImageStatus::ACCEPTED, $goodReloaded->getStatus());
    }

    public function testProcessBatch_oversizedImageStaysPendingWithoutDecoding(): void
    {
        $wizard = $this->createUser(AccessLevel::WIZARD);
        $oversizedImage = self::createTestImage(ImageDimensions::MAX_DIMENSION + 1, 1);
        $originalContent = self::encodeImage($oversizedImage, ImageType::PNG);
        $entity = $this->createPendingImageFromContent($originalContent, ImageType::PNG, $wizard->getId());

        self::assertSame(0, $this->testee->processBatch(10));

        $reloaded = $this->imageEntityService->findOneById($entity->getId());
        self::assertNotNull($reloaded);
        self::assertSame(ImageStatus::PENDING, $reloaded->getStatus());
        self::assertNull($reloaded->getThumbFilename());
        self::assertNull($reloaded->getSampleFilename());
        self::assertNull($reloaded->getDownloadFilename());
        self::assertFileExists($this->originalPath($entity));
    }

    public function testProcessBatch_missingOriginalFile_staysPendingWithoutException(): void
    {
        $wizard = $this->createUser(AccessLevel::WIZARD);
        $missingChecksum = hash('sha256', 'never-stored-original-' . bin2hex(random_bytes(8)));
        $entity = new ImageEntity(
            id: null,
            userId: $wizard->getId(),
            uploadDate: new DateTime(),
            status: ImageStatus::PENDING,
            checksum: $missingChecksum,
            originalExtension: 'png',
        );
        $this->imageEntityService->saveAsNew($entity);

        $processed = $this->testee->processBatch(10);

        self::assertSame(0, $processed);
        $reloaded = $this->imageEntityService->findOneById($entity->getId());
        self::assertNotNull($reloaded);
        self::assertSame(ImageStatus::PENDING, $reloaded->getStatus());
        self::assertNull($reloaded->getThumbFilename());
    }

    public function testProcessBatch_deletedUploader_keepsImageInReviewQueue(): void
    {
        $entity = $this->createPendingImage(self::createTestImage(10, 10), ImageType::PNG, 987654321);

        $processed = $this->testee->processBatch(10);

        self::assertSame(1, $processed);
        $reloaded = $this->imageEntityService->findOneById($entity->getId());
        self::assertNotNull($reloaded);
        self::assertSame(987654321, $reloaded->getUserId());
        self::assertSame(ImageStatus::PENDING_REVIEW, $reloaded->getStatus());
        self::assertNotNull($reloaded->getThumbFilename());
    }

    public function testProcessBatch_respectsBatchLimitAndOldestFirstOrder(): void
    {
        $wizard = $this->createUser(AccessLevel::WIZARD);
        $oldest = $this->createPendingImage(self::createTestImage(8, 8), ImageType::PNG, $wizard->getId(), new DateTime('2026-01-01 00:00:00'));
        $middle = $this->createPendingImage(self::createTestImage(8, 8), ImageType::PNG, $wizard->getId(), new DateTime('2026-01-02 00:00:00'));
        $newest = $this->createPendingImage(self::createTestImage(8, 8), ImageType::PNG, $wizard->getId(), new DateTime('2026-01-03 00:00:00'));

        $processed = $this->testee->processBatch(2);

        self::assertSame(2, $processed);
        self::assertSame(ImageStatus::ACCEPTED, $this->imageEntityService->findOneById($oldest->getId())->getStatus());
        self::assertSame(ImageStatus::ACCEPTED, $this->imageEntityService->findOneById($middle->getId())->getStatus());
        self::assertSame(ImageStatus::PENDING, $this->imageEntityService->findOneById($newest->getId())->getStatus());

        $this->testee->processBatch(2);

        self::assertSame(ImageStatus::ACCEPTED, $this->imageEntityService->findOneById($newest->getId())->getStatus());
    }

    public function testProcessBatch_noPendingImages_returnsZero(): void
    {
        self::assertSame(0, $this->testee->processBatch(10));
    }

    public function testProcessBatch_nonPositiveBatchSize_throwsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains('Batch size must be a positive integer.');

        $this->testee->processBatch(0);
    }

    private static function encodeImage(GdImage $image, ImageType $imageType, ?int $quality = null): string
    {
        $obLevel = ob_get_level();
        ob_start();
        try {
            $success = match ($imageType) {
                ImageType::PNG => imagepng($image),
                ImageType::JPEG => $quality === null ? imagejpeg($image) : imagejpeg($image, null, $quality),
                ImageType::AVIF => $quality === null ? imageavif($image) : imageavif($image, null, $quality),
                ImageType::WEBP => $quality === null ? imagewebp($image) : imagewebp($image, null, $quality),
            };
            $content = ob_get_clean();
        } finally {
            if (ob_get_level() > $obLevel) {
                ob_end_clean();
            }
        }
        if (!$success || $content === false || $content === '') {
            self::fail('Failed to encode test image as ' . $imageType->value);
        }
        return $content;
    }

    private static function jpegWithExifApp1Segment(string $jpeg): string
    {
        $exifPayload = "Exif\x00\x00" . "MM\x00\x2A\x00\x00\x00\x08\x00\x00";
        $app1Segment = "\xFF\xE1" . pack('n', strlen($exifPayload) + 2) . $exifPayload;
        return "\xFF\xD8" . $app1Segment . substr($jpeg, 2);
    }
}
