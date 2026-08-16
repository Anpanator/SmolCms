<?php

declare(strict_types=1);

namespace SmolCms\Data\Image;

interface ImageTypeDetector
{
    /**
     * Detects if the given file content matches the image type signature
     * Returns the ImageType enum if detected, null otherwise
     */
    public function getImageType(string $fileContent): ?ImageType;
}
