<?php

declare(strict_types=1);

namespace SmolCms\Service\Image;

use SmolCms\Data\Image\ImageType;
use SmolCms\Data\Image\ImageTypeDetector;

readonly class JpegImageTypeDetector implements ImageTypeDetector
{
    public function getImageType(string $fileContent): ?ImageType
    {
        // JPEG signature: starts with FF D8 FF
        $length = 3;

        if (strlen($fileContent) >= $length &&
            substr($fileContent, 0, $length) === pack('H*', 'FFD8FF')) {
            return ImageType::JPEG;
        }

        return null;
    }
}
