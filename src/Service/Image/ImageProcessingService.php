<?php

declare(strict_types=1);

namespace SmolCms\Service\Image;

use ErrorException;
use GdImage;
use InvalidArgumentException;
use RuntimeException;
use SmolCms\Data\Constant\AccessLevel;
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
 * Batch processor for originals in the flat upload directory. Each original's
 * checksum and extension locate its image rows. Partial rows are regenerated from
 * scratch; if every matching row is complete, the leftover original is warned about
 * and deleted without re-encoding it. Variant files are content-addressed, so a
 * retry overwrites the same paths safely.
 *
 * A failure on one original is logged and left in the upload directory for retry;
 * the rest of the batch is still processed.
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
     * Processes up to $batchSize incomplete image records (oldest uploads first).
     *
     * @return int The number of image records successfully processed.
     */
    public function processBatch(int $batchSize): int
    {
        if ($batchSize < 1) {
            throw new InvalidArgumentException('Batch size must be a positive integer.');
        }

        $candidates = $this->findOriginalCandidates();
        usort($candidates, static function (array $left, array $right): int {
            if ($left['uploadDate'] === null || $right['uploadDate'] === null) {
                if ($left['uploadDate'] === $right['uploadDate']) {
                    return strcmp($left['fileName'], $right['fileName']);
                }
                return $left['uploadDate'] === null ? 1 : -1;
            }
            $dateOrder = $left['uploadDate'] <=> $right['uploadDate'];
            return $dateOrder !== 0 ? $dateOrder : strcmp($left['fileName'], $right['fileName']);
        });

        $processed = 0;
        foreach ($candidates as $candidate) {
            try {
                $processed += $this->processOriginal($candidate, max(0, $batchSize - $processed));
            } catch (Throwable $t) {
                $this->loggingService->log(
                    LogLevel::ERROR,
                    "Failed to process original {$candidate['fileName']}: {$t->getMessage()}"
                );
            }
        }
        return $processed;
    }

    /**
     * @return list<array{
     *     fileName: string,
     *     checksum: string,
     *     extension: string,
     *     entities: list<ImageEntity>,
     *     uploadDate: \DateTime|null
     * }>
     */
    private function findOriginalCandidates(): array
    {
        $candidates = [];
        foreach ($this->fileSystemAccessService->listFiles($this->originalStoragePath) as $fileName) {
            $parsed = $this->parseOriginalFileName($fileName);
            if ($parsed === null) {
                continue;
            }

            $entities = $this->imageEntityService->findByOriginalChecksum(
                $parsed['checksum'],
                $parsed['extension'],
            );
            if ($entities === []) {
                $candidates[] = [
                    'fileName' => $fileName,
                    'checksum' => $parsed['checksum'],
                    'extension' => $parsed['extension'],
                    'entities' => [],
                    'uploadDate' => null,
                ];
                continue;
            }

            usort($entities, static function (ImageEntity $left, ImageEntity $right): int {
                $dateOrder = $left->getUploadDate() <=> $right->getUploadDate();
                return $dateOrder !== 0 ? $dateOrder : ($left->getId() <=> $right->getId());
            });

            $uploadDate = $entities[0]->getUploadDate();
            foreach ($entities as $entity) {
                if ($entity->getUploadDate() < $uploadDate) {
                    $uploadDate = $entity->getUploadDate();
                }
            }

            $candidates[] = [
                'fileName' => $fileName,
                'checksum' => $parsed['checksum'],
                'extension' => $parsed['extension'],
                'entities' => $entities,
                'uploadDate' => $uploadDate,
            ];
        }

        return $candidates;
    }

    /**
     * @return array{checksum: string, extension: string}|null
     */
    private function parseOriginalFileName(string $fileName): ?array
    {
        if (preg_match('/\A([0-9a-f]{64})\.([a-z]+)\z/', $fileName, $matches) !== 1) {
            return null;
        }
        if (ImageType::tryFrom($matches[2]) === null) {
            return null;
        }
        return ['checksum' => $matches[1], 'extension' => $matches[2]];
    }

    /**
     * @param array{
     *     fileName: string,
     *     checksum: string,
     *     extension: string,
     *     entities: list<ImageEntity>,
     *     uploadDate: \DateTime|null
     * } $candidate
     */
    private function processOriginal(array $candidate, int $remainingBatchSize): int
    {
        if ($candidate['entities'] === []) {
            $this->loggingService->log(
                LogLevel::WARNING,
                "Original {$candidate['fileName']} has no matching database image; deleting the orphaned original."
            );
            $this->deleteOriginalFile($candidate['fileName']);
            return 0;
        }

        $partialEntities = array_values(array_filter(
            $candidate['entities'],
            fn(ImageEntity $entity): bool => !$this->isFullyProcessed($entity),
        ));

        if ($partialEntities === []) {
            $this->loggingService->log(
                LogLevel::WARNING,
                "Original {$candidate['fileName']} is already fully processed; skipping processing and deleting it."
            );
            $this->deleteOriginalFile($candidate['fileName']);
            return 0;
        }

        $entitiesToProcess = array_values(array_filter(
            $partialEntities,
            static fn(ImageEntity $entity): bool => !$entity->isDeleted(),
        ));
        if ($entitiesToProcess === []) {
            // Keep originals for queued, unprocessed records in case they are restored.
            return 0;
        }
        if ($remainingBatchSize === 0) {
            return 0;
        }
        $entitiesToProcess = array_slice($entitiesToProcess, 0, $remainingBatchSize);

        $originalPath = $this->originalStoragePath . '/' . $candidate['fileName'];
        $originalContent = $this->fileSystemAccessService->readFile($originalPath);
        if (hash('sha256', $originalContent) !== $candidate['checksum']) {
            throw new RuntimeException("Original {$candidate['fileName']} does not match its checksum.");
        }

        $variants = $this->createVariants($originalContent, $entitiesToProcess[0]);
        $processed = 0;
        $saveFailed = false;
        foreach ($entitiesToProcess as $entity) {
            try {
                $entity->setThumbFilename($variants['thumbFilename']);
                $entity->setSampleFilename($variants['sampleFilename']);
                $entity->setDownloadFilename($variants['downloadFilename']);
                $entity->setWidth($variants['width']);
                $entity->setHeight($variants['height']);
                $entity->setFormat($variants['format']);
                $entity->setFileSize($variants['fileSize']);
                if ($entity->getStatus() === ImageStatus::PENDING) {
                    $entity->setStatus($this->resolvePostProcessingStatus($entity));
                }
                $this->imageEntityService->saveOrUpdate($entity);
                $processed++;
            } catch (Throwable $t) {
                $saveFailed = true;
                $this->loggingService->log(
                    LogLevel::ERROR,
                    "Failed to process image {$entity->getId()}: {$t->getMessage()}"
                );
            }
        }
        if ($saveFailed) {
            return $processed;
        }

        foreach ($candidate['entities'] as $entity) {
            if (!$this->isFullyProcessed($entity)) {
                return $processed;
            }
        }

        $this->deleteOriginalFile($candidate['fileName']);
        return $processed;
    }

    private function isFullyProcessed(ImageEntity $entity): bool
    {
        return $entity->getStatus() !== ImageStatus::PENDING
            && $entity->getThumbFilename() !== null
            && $entity->getSampleFilename() !== null
            && $entity->getDownloadFilename() !== null
            && $entity->getWidth() !== null
            && $entity->getHeight() !== null
            && $entity->getFormat() !== null
            && $entity->getFileSize() !== null;
    }

    /**
     * Rebuilds and stores every variant from source pixels. Existing content-addressed
     * files are overwritten, which makes recovery from a partial row deterministic.
     *
     * @return array{
     *     thumbFilename: string,
     *     sampleFilename: string,
     *     downloadFilename: string,
     *     width: int,
     *     height: int,
     *     format: string,
     *     fileSize: int
     * }
     */
    private function createVariants(string $originalContent, ImageEntity $entity): array
    {
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

        $thumbFilename = $this->storeVariant($this->thumbStorage, $thumbContent, ImageType::AVIF);
        $sampleFilename = $this->storeVariant($this->sampleStorage, $sampleContent, ImageType::WEBP);
        $this->downloadStorage->storeBytes($downloadContent, ImagePathLayout::relativePath($downloadChecksum, $downloadExtension));
        return [
            'thumbFilename' => $thumbFilename,
            'sampleFilename' => $sampleFilename,
            'downloadFilename' => ImagePathLayout::fileName($downloadChecksum, $downloadExtension),
            'width' => imagesx($downloadImage),
            'height' => imagesy($downloadImage),
            'format' => $downloadExtension,
            'fileSize' => strlen($downloadContent),
        ];
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

    private function deleteOriginalFile(string $fileName): void
    {
        $originalPath = $this->originalStoragePath . '/' . $fileName;
        try {
            if (!$this->fileSystemAccessService->fileExists($originalPath)) {
                return;
            }
            $this->fileSystemAccessService->deleteFile($originalPath);
        } catch (Throwable $e) {
            $this->loggingService->log(
                LogLevel::WARNING,
                "Could not delete original {$fileName}: {$e->getMessage()}"
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
