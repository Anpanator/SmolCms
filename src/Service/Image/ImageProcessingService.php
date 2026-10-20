<?php

declare(strict_types=1);

namespace SmolCms\Service\Image;

use ErrorException;
use GdImage;
use InvalidArgumentException;
use RuntimeException;
use SmolCms\Data\Constant\AccessLevel;
use SmolCms\Data\Constant\ImageFileVariant;
use SmolCms\Data\Constant\ImageStatus;
use SmolCms\Data\Constant\LogLevel;
use SmolCms\Data\Image\ImageType;
use SmolCms\Data\Persistence\ImageEntity;
use SmolCms\Service\Core\LoggingService;
use SmolCms\Service\DB\ImageEntityService;
use SmolCms\Service\DB\UserService;
use SmolCms\Service\File\FileSystemAccessService;
use Throwable;

/**
 * Batch processor for uploaded images in PENDING state. For every image it decodes the
 * original and generates three variants — thumbnail (AVIF), sample/viewing
 * image (WebP) and download image (original format, or lossless WebP for PNG sources) —
 * each encoded from GD-decoded pixels rather than copied from the source bytes, so
 * source metadata or trailing payloads cannot reach the served variants.
 * then updates the entity with the variant filenames, the download image's metadata and
 * the next lifecycle status. Variant files are content-addressed and the entity update
 * is repeatable, so an interrupted image can safely be processed again while its original
 * is present. Original-file deletion is deferred until after processing and retried for
 * completed rows on subsequent worker runs.
 *
 * A failure on a single image is logged and that row is left PENDING (retried on the
 * next run); the rest of the batch is still processed.
 */
class ImageProcessingService
{
    private const int THUMB_MAX_SIZE = 512;
    private const int SAMPLE_MAX_SIZE = 2048;
    private const int DOWNLOAD_MAX_SIZE = 16384;
    private const int VARIANT_QUALITY = 66;

    public function __construct(
        private ImageEntityService $imageEntityService,
        private UserService $userService,
        private ImageStorageService $thumbStorage,
        private ImageStorageService $sampleStorage,
        private ImageStorageService $downloadStorage,
        private string $originalStoragePath,
        private LoggingService $loggingService,
        private FileSystemAccessService $fileSystemAccessService,
    ) {
    }

    /**
     * Processes up to $batchSize pending images (oldest uploads first).
     *
     * @return int The number of successfully processed images.
     */
    public function processBatch(int $batchSize): int
    {
        if ($batchSize < 1) {
            throw new InvalidArgumentException('Batch size must be a positive integer.');
        }

        $processed = 0;
        $entities = $this->imageEntityService->findByStatus(ImageStatus::PENDING, $batchSize);
        foreach ($entities as $entity) {
            try {
                $this->processImage($entity);
                $processed++;
            } catch (Throwable $t) {
                // Error policy: one broken image must never abort the run. The row stays
                // PENDING so it is retried on the next batch.
                $this->loggingService->log(
                    LogLevel::ERROR,
                    "Failed to process image {$entity->getId()}: {$t->getMessage()}"
                );
            }
        }
        // Deleting originals is the final phase. If the worker stops before it, the
        // completed rows still identify which originals need cleanup on the next run.
        $this->deleteOriginalsAfterProcessing();
        return $processed;
    }

    private function processImage(ImageEntity $entity): void
    {
        $originalContent = $this->readOriginal($entity);
        $image = $this->decodeOriginal($originalContent, $entity);
        $originalType = ImageType::from($entity->getOriginalExtension());

        $thumbImage = ImageResizer::resizeToFit($image, self::THUMB_MAX_SIZE, self::THUMB_MAX_SIZE);
        $thumbContent = $this->encode($thumbImage, ImageType::AVIF, self::VARIANT_QUALITY);

        $sampleImage = ImageResizer::resizeToFit($image, self::SAMPLE_MAX_SIZE, self::SAMPLE_MAX_SIZE);
        $sampleContent = $this->encode($sampleImage, ImageType::WEBP, self::VARIANT_QUALITY);

        $downloadImage = ImageResizer::resizeToFit($image, self::DOWNLOAD_MAX_SIZE, self::DOWNLOAD_MAX_SIZE);
        [$downloadContent, $downloadType] = $this->buildDownloadContent($downloadImage, $originalType);

        // The frontend metadata sidebar shows the download image's dimensions, format and
        // file size, so the entity metadata mirrors the download variant.
        $downloadChecksum = hash('sha256', $downloadContent);
        $downloadExtension = $downloadType->getFileExtension();

        $entity->setThumbFilename($this->storeVariant($this->thumbStorage, $thumbContent, ImageType::AVIF));
        $entity->setSampleFilename($this->storeVariant($this->sampleStorage, $sampleContent, ImageType::WEBP));
        $this->downloadStorage->storeBytes($downloadContent, ImagePathLayout::relativePath($downloadChecksum, $downloadExtension));
        $entity->setDownloadFilename(ImagePathLayout::fileName($downloadChecksum, $downloadExtension));
        $entity->setWidth(imagesx($downloadImage));
        $entity->setHeight(imagesy($downloadImage));
        $entity->setFormat($downloadExtension);
        $entity->setFileSize(strlen($downloadContent));
        $entity->setStatus($this->resolvePostProcessingStatus($entity));
        $this->imageEntityService->saveOrUpdate($entity);
    }

    private function decodeOriginal(string $content, ImageEntity $entity): GdImage
    {
        if (ImageDimensions::fromString($content) === null) {
            throw new RuntimeException(
                "Stored original of image {$entity->getId()} has invalid or over-limit dimensions."
            );
        }

        set_error_handler(
            static function (int $severity, string $message, string $file, int $line): never {
                throw new ErrorException($message, 0, $severity, $file, $line);
            }
        );
        try {
            $image = imagecreatefromstring($content);
        } finally {
            restore_error_handler();
        }

        if ($image === false) {
            throw new RuntimeException("Stored original of image {$entity->getId()} is not decodable image data.");
        }
        return $image;
    }

    private function deleteOriginalsAfterProcessing(): void
    {
        $seenOriginals = [];
        foreach ($this->imageEntityService->findProcessedImages() as $entity) {
            $originalKey = $entity->getChecksum() . "\0" . $entity->getOriginalExtension();
            if (isset($seenOriginals[$originalKey])) {
                continue;
            }
            $seenOriginals[$originalKey] = true;
            $this->deleteOriginalAfterProcessing($entity);
        }
    }

    private function deleteOriginalAfterProcessing(ImageEntity $entity): void
    {
        try {
            if ($this->imageEntityService->hasOtherImageReference($entity, ImageFileVariant::ORIGINAL)) {
                return;
            }

            $originalPath = $this->originalStoragePath . '/' . ImagePathLayout::relativePath(
                    $entity->getChecksum(),
                    $entity->getOriginalExtension()
                );
            if (!$this->fileSystemAccessService->fileExists($originalPath)) {
                return;
            }
            $this->fileSystemAccessService->deleteFile($originalPath);
        } catch (Throwable $e) {
            $this->loggingService->log(
                LogLevel::WARNING,
                "Could not delete processed original for image {$entity->getId()}: {$e->getMessage()}"
            );
        }
    }

    /**
     * Re-encodes the download variant in its original format, except PNG which is
     * converted to pixel-lossless WebP as before. GD cannot reliably recover an image's
     * original encoder settings, so use maximum JPEG quality, lossless WebP, and AVIF
     * quality 75. Re-encoding also removes metadata such as EXIF.
     *
     * @return array{0: string, 1: ImageType} The download bytes and the format they are stored in.
     */
    private function buildDownloadContent(GdImage $downloadImage, ImageType $originalType): array
    {
        return match ($originalType) {
            ImageType::PNG => [$this->encode($downloadImage, ImageType::WEBP, IMG_WEBP_LOSSLESS), ImageType::WEBP],
            ImageType::JPEG => [$this->encode($downloadImage, ImageType::JPEG, 100), ImageType::JPEG],
            ImageType::WEBP => [$this->encode($downloadImage, ImageType::WEBP, IMG_WEBP_LOSSLESS), ImageType::WEBP],
            ImageType::AVIF => [$this->encode($downloadImage, ImageType::AVIF, 75), ImageType::AVIF],
        };
    }

    private function resolvePostProcessingStatus(ImageEntity $entity): ImageStatus
    {
        $uploader = $this->userService->findOneById($entity->getUserId());
        if ($uploader === null) {
            $this->loggingService->log(
                LogLevel::ERROR,
                "Uploader of image {$entity->getId()} no longer exists; keeping it in the review queue."
            );
            return ImageStatus::PENDING_REVIEW;
        }
        return $uploader->getAccessLevel()->value >= AccessLevel::WIZARD->value
            ? ImageStatus::ACCEPTED
            : ImageStatus::PENDING_REVIEW;
    }

    private function readOriginal(ImageEntity $entity): string
    {
        $relativePath = ImagePathLayout::relativePath($entity->getChecksum(), $entity->getOriginalExtension());
        return $this->fileSystemAccessService->readFile($this->originalStoragePath . '/' . $relativePath);
    }

    /**
     * Writes pre-encoded variant bytes below the storage's base directory, named after
     * the SHA-256 checksum of the content itself (see ImagePathLayout), and returns the
     * stored file name.
     */
    private function storeVariant(ImageStorageService $storage, string $content, ImageType $imageType): string
    {
        $checksum = hash('sha256', $content);
        $storage->storeBytes($content, ImagePathLayout::relativePath($checksum, $imageType->getFileExtension()));
        return ImagePathLayout::fileName($checksum, $imageType->getFileExtension());
    }

    private function encode(GdImage $image, ImageType $imageType, int $quality): string
    {
        $obLevel = ob_get_level();
        ob_start();
        try {
            $success = match ($imageType) {
                ImageType::PNG => imagepng($image, null, 9),
                ImageType::JPEG => imagejpeg($image, null, $quality),
                ImageType::AVIF => imageavif($image, null, $quality),
                ImageType::WEBP => imagewebp($image, null, $quality),
            };
            $content = ob_get_clean();
        } finally {
            if (ob_get_level() > $obLevel) {
                ob_end_clean();
            }
        }
        if (!$success || $content === false || $content === '') {
            throw new RuntimeException("Failed to encode image as {$imageType->value} with quality $quality.");
        }
        return $content;
    }
}
