<?php

declare(strict_types=1);

namespace SmolCms\Service\Image;

use SmolCms\Data\Image\ImageType;
use SmolCms\Data\Image\ImageTypeDetector;

readonly class WebpImageTypeDetector implements ImageTypeDetector
{
    public function getImageType(string $fileContent): ?ImageType
    {
        // WebP signature: RIFF container marker at offset 0 and the WEBP tag at offset 8
        if (strlen($fileContent) >= 12
            && substr($fileContent, 0, 4) === 'RIFF'
            && substr($fileContent, 8, 4) === 'WEBP'
        ) {
            return ImageType::WEBP;
        }

        return null;
    }
}
