<?php
declare(strict_types=1);

namespace SmolCms\Service\Image;

use GdImage;
use InvalidArgumentException;
use SmolCms\Data\Image\ImageType;

readonly class JpegImageProcessor implements ImageProcessorInterface
{
    public function getType(): ImageType
    {
        return ImageType::JPEG;
    }

    public function sanitize(string $imageData): GdImage
    {
        // Validate and decode image data using GD extension
        $image = imagecreatefromstring($imageData);
        if ($image === false) {
            throw new InvalidArgumentException('Invalid JPEG image data');
        }

        return $image;
    }
}
