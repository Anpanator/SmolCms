<?php

declare(strict_types=1);

namespace SmolCms\Service\Image;

use SmolCms\Data\Image\ImageType;
use SmolCms\Data\Image\ImageTypeDetector;

readonly class AvifImageTypeDetector implements ImageTypeDetector
{
    public function getImageType(string $fileContent): ?ImageType
    {
        // AVIF signature: ftypavif or ftypavis (at offset 4)
        // The brand marker "ftyp" is at offset 4, followed by the brand code
        $length = 8;

        if (strlen($fileContent) >= 12 && (
                substr($fileContent, 4, $length) === 'ftypavif' ||
                substr($fileContent, 4, $length) === 'ftypavis'
            )) {
            return ImageType::AVIF;
        }

        return null;
    }
}
