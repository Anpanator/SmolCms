<?php
declare(strict_types=1);

namespace SmolCms\Service\Image;

use GdImage;
use SmolCms\Data\Image\ImageType;

interface ImageProcessorInterface
{
    /**
     * The image type this processor handles. ImageProcessorFacade uses it to pick
     * exactly one processor for a detected type.
     */
    public function getType(): ImageType;

    /**
     * Decodes and validates image data into a GdImage. Encoding that image later
     * produces pixel data without source metadata such as EXIF.
     */
    public function sanitize(string $imageData): GdImage;
}
