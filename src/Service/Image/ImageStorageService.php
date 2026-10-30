<?php
declare(strict_types=1);

namespace SmolCms\Service\Image;

use GdImage;
use RuntimeException;
use SmolCms\Data\Image\ImageType;
use SmolCms\Service\File\FileSystemAccessService;

readonly class ImageStorageService
{
    public function __construct(
        private string                  $storagePath,
        private FileSystemAccessService $fileSystemAccessService,
    )
    {
    }

    /**
     * Encodes a GD image as the given type and stores the result under its SHA-256
     * checksum (see ImagePathLayout). Returns the metadata of the stored file.
     */
    public function storeImage(GdImage $image, ImageType $imageType): StoredImage
    {
        $content = $this->encode($image, $imageType);
        return $this->storeContent($content, $imageType, imagesx($image), imagesy($image));
    }

    /**
     * Stores the original uploaded bytes without re-encoding them. The decoded image
     * is used only to capture its dimensions; the image processing worker performs
     * any required re-encoding later.
     */
    public function storeOriginal(string $content, ImageType $imageType, GdImage $image): StoredImage
    {
        return $this->storeContent($content, $imageType, imagesx($image), imagesy($image), true);
    }

    private function storeContent(
        string $content,
        ImageType $imageType,
        int $width,
        int $height,
        bool $flat = false,
    ): StoredImage
    {
        $checksum = hash('sha256', $content);
        $extension = $imageType->getFileExtension();
        $relativePath = $flat
            ? ImagePathLayout::originalRelativePath($checksum, $extension)
            : ImagePathLayout::relativePath($checksum, $extension);
        $this->storeBytes($content, $relativePath);

        return new StoredImage(
            checksum: $checksum,
            filename: ImagePathLayout::fileName($checksum, $extension),
            relativePath: $relativePath,
            width: $width,
            height: $height,
            fileSize: strlen($content),
            type: $imageType,
        );
    }

    /**
     * Writes pre-encoded image bytes at the given relative path below the configured
     * storage path. Creates missing subdirectories and checks them against the
     * directory whitelist. Returns the absolute path of the stored file.
     */
    public function storeBytes(string $content, string $relativePath): string
    {
        $fullPath = $this->storagePath . '/' . $relativePath;
        $subdirectory = dirname($fullPath);
        $this->fileSystemAccessService->ensureDirectory($subdirectory);
        $this->fileSystemAccessService->writeFile($fullPath, $content);
        return $fullPath;
    }

    public function getStoragePath(): string
    {
        return $this->storagePath;
    }

    private function encode(GdImage $image, ImageType $imageType): string
    {
        $obLevel = ob_get_level();
        ob_start();
        try {
            $success = match ($imageType) {
                ImageType::PNG => imagepng($image, null, 9),
                ImageType::JPEG => imagejpeg($image, null, 100),
                ImageType::AVIF => imageavif($image, null, 100),
                ImageType::WEBP => imagewebp($image, null, 100),
            };
            $content = ob_get_clean();
        } finally {
            if (ob_get_level() > $obLevel) {
                ob_end_clean();
            }
        }
        if (!$success || $content === false || $content === '') {
            throw new RuntimeException("Failed to encode image as {$imageType->value}");
        }
        return $content;
    }
}
