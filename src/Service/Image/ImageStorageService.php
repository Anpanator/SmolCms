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
     * Encodes the image as the given type, names the file after the SHA-256 checksum of
     * the encoded bytes (see ImagePathLayout) and writes it below the configured storage
     * path. Returns the metadata of the stored file.
     */
    public function storeImage(GdImage $image, ImageType $imageType): StoredImage
    {
        $content = $this->encode($image, $imageType);
        $checksum = hash('sha256', $content);
        $extension = $imageType->getFileExtension();
        $this->storeBytes($content, ImagePathLayout::relativePath($checksum, $extension));

        return new StoredImage(
            checksum: $checksum,
            filename: ImagePathLayout::fileName($checksum, $extension),
            width: imagesx($image),
            height: imagesy($image),
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
        if (!is_dir($subdirectory) && !mkdir($subdirectory, 0777, true) && !is_dir($subdirectory)) {
            throw new RuntimeException("Could not create image storage directory: $subdirectory");
        }
        $resolvedSubdirectory = realpath($subdirectory);
        if ($resolvedSubdirectory === false) {
            throw new RuntimeException("Could not resolve directory for image storage path: $fullPath");
        }
        $this->fileSystemAccessService->isPathAllowed($resolvedSubdirectory) ?:
            throw new RuntimeException('Image storage is not allowed in path. Check config!');

        if (is_link($fullPath)) {
            throw new RuntimeException("Refusing to write through symlink at path: $fullPath");
        }
        if (file_put_contents($fullPath, $content) === false) {
            throw new RuntimeException("Failed to write image to path: $fullPath");
        }
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
