<?php
declare(strict_types=1);

namespace SmolCms\Service\Image;

use InvalidArgumentException;
use SmolCms\Data\Image\ImageType;

readonly class PngImageProcessor implements ImageProcessorInterface
{
    public function __construct(
        private ImageStorageService $storageService
    )
    {
    }

    public function process(string $imageData, string $filename): void
    {
        // Validate and process PNG image using GD extension
        $image = imagecreatefromstring($imageData);
        if ($image === false) {
            throw new InvalidArgumentException('Invalid PNG image data');
        }

        // Store the processed image
        $this->storageService->storeImage($image, ImageType::PNG, $filename);
    }
}

