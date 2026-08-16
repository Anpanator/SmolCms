<?php
declare(strict_types=1);

namespace SmolCms\Service\Image;

use InvalidArgumentException;
use SmolCms\Data\Image\ImageType;

readonly class JpegImageProcessor implements ImageProcessorInterface
{
    public function __construct(
        private ImageStorageService $storageService
    )
    {
    }

    public function process(string $imageData, string $filename): void
    {
        // Validate and process JPEG image using GD extension
        $image = imagecreatefromstring($imageData);
        if ($image === false) {
            throw new InvalidArgumentException('Invalid JPEG image data');
        }

        // Store the processed image
        $this->storageService->storeImage($image, ImageType::JPEG, $filename);
    }
}

