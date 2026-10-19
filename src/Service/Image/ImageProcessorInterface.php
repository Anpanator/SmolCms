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
     * Decodes and validates image data into a GdImage.
     *
     * Sanitizing happens by decoding the raw upload into pure pixel data: encoding
     * that GdImage (ImageStorageService) strips any EXIF/metadata and produces a file
     * that truly is the claimed format.
     */
    public function sanitize(string $imageData): GdImage;
}
