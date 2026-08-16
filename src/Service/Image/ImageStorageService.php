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

    public function storeImage(GdImage $image, ImageType $imageType, string $filename): void
    {
        $fullPath = $this->storagePath . '/' . $filename;
        $resolvedDir = realpath(dirname($fullPath));
        if ($resolvedDir === false) {
            throw new RuntimeException("Could not resolve directory for image storage path: $fullPath");
        }
        $this->fileSystemAccessService->isPathAllowed($resolvedDir) ?:
            throw new RuntimeException('Image storage is not allowed in path. Check config!');

        switch ($imageType) {
            case ImageType::PNG:
                imagepng($image, $fullPath, 9);
                break;
            case ImageType::JPEG:
                imagejpeg($image, $fullPath, 100);
                break;
            case ImageType::AVIF:
                imageavif($image, $fullPath, 100);
                break;
        }

        if (!file_exists($fullPath)) {
            throw new RuntimeException("Failed to write image to path: $fullPath");
        }
    }

    public function getStoragePath(): string
    {
        return $this->storagePath;
    }
}

