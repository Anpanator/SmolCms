<?php
declare(strict_types=1);

namespace SmolCms\TestUtils;

use DateTime;
use SmolCms\Data\Constant\ImageStatus;
use SmolCms\Data\Persistence\ImageEntity;
use SmolCms\Service\DB\ImageEntityService;
use SmolCms\Service\Image\ImagePathLayout;

/**
 * Shared helpers for image-related functional tests: creates image rows with
 * controlled data and writes/cleans dummy image files below the configured
 * image directories. For logging in users above NOVICE see LoginWithAccessLevel.
 */
final class ImageTestUtils
{
    /** @var array<string> All configured image base directories. */
    public const IMAGE_BASE_DIRS = [
        IMAGE_STORAGE_DIR,
        IMAGE_THUMB_DIR,
        IMAGE_SAMPLE_DIR,
        IMAGE_DOWNLOAD_DIR,
    ];

    private function __construct()
    {
    }

    /**
     * Creates and persists an image entity with a random, valid checksum and
     * freely configurable status, upload date and variant file names.
     */
    public static function createImageEntity(
        ImageEntityService $imageEntityService,
        int                $userId,
        ImageStatus        $status = ImageStatus::ACCEPTED,
        ?DateTime          $uploadDate = null,
        ?string            $checksum = null,
        ?string            $thumbFilename = null,
        ?string            $sampleFilename = null,
        ?string            $downloadFilename = null,
        ?DateTime          $deletedAt = null,
    ): ImageEntity
    {
        $entity = new ImageEntity(
            id: null,
            userId: $userId,
            uploadDate: $uploadDate ?? new DateTime(),
            status: $status,
            checksum: $checksum ?? self::randomChecksum(),
            originalExtension: 'jpeg',
            thumbFilename: $thumbFilename,
            sampleFilename: $sampleFilename,
            downloadFilename: $downloadFilename,
            width: 100,
            height: 50,
            format: 'jpeg',
            fileSize: 1234,
            deletedAt: $deletedAt,
        );
        $imageEntityService->saveAsNew($entity);
        return $entity;
    }

    public static function randomChecksum(): string
    {
        return bin2hex(random_bytes(32));
    }

    /**
     * Absolute on-disk path of an image file with the given stored file name
     * below the given base directory (two-character subdirectory layout).
     */
    public static function imageFilePath(string $baseDir, string $fileName): string
    {
        return $baseDir . '/' . ImagePathLayout::relativePathForFileName($fileName);
    }

    /**
     * Writes a dummy image file below the given base directory.
     */
    public static function writeImageFile(string $baseDir, string $fileName, string $content = 'dummy-image-data'): void
    {
        $path = self::imageFilePath($baseDir, $fileName);
        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0777, true);
        }
        file_put_contents($path, $content);
    }

    /**
     * Removes every non-hidden file and subdirectory from all configured image
     * directories. Hidden entries (e.g. .gitkeep) are preserved.
     */
    public static function cleanImageDirs(): void
    {
        foreach (self::IMAGE_BASE_DIRS as $baseDir) {
            if (!is_dir($baseDir)) {
                continue;
            }
            $entries = scandir($baseDir);
            if ($entries === false) {
                continue;
            }
            foreach ($entries as $entry) {
                if ($entry === '.' || $entry === '..' || str_starts_with($entry, '.')) {
                    continue;
                }
                $entryPath = $baseDir . '/' . $entry;
                if (is_dir($entryPath)) {
                    self::removeDirectoryRecursive($entryPath);
                } else {
                    unlink($entryPath);
                }
            }
        }
    }

    private static function removeDirectoryRecursive(string $path): void
    {
        $entries = scandir($path);
        if ($entries === false) {
            return;
        }
        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $entryPath = $path . '/' . $entry;
            if (is_dir($entryPath)) {
                self::removeDirectoryRecursive($entryPath);
            } else {
                unlink($entryPath);
            }
        }
        rmdir($path);
    }
}
