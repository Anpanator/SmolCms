<?php

declare(strict_types=1);

namespace SmolCms\Service\Image;

use SmolCms\Data\Image\ImageType;
use SmolCms\Data\Image\ImageTypeDetector;

readonly class PngImageTypeDetector implements ImageTypeDetector
{
    public function getImageType(string $fileContent): ?ImageType
    {
        // PNG signature: 89 50 4E 47 0D 0A 1A 0A
        $signature = pack('H*', '89504E47');
        $length = 4;

        if (strlen($fileContent) >= $length &&
            substr($fileContent, 0, $length) === $signature) {
            return ImageType::PNG;
        }

        return null;
    }
}
