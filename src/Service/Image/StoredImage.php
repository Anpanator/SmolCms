<?php
declare(strict_types=1);

namespace SmolCms\Service\Image;

use SmolCms\Data\Image\ImageType;

/**
 * Result of storing an image: the content checksum the file is named after
 * plus the dimensions and byte size of the stored file.
 */
readonly class StoredImage
{
    public function __construct(
        private string    $checksum,
        private string    $filename,
        private int       $width,
        private int       $height,
        private int       $fileSize,
        private ImageType $type,
    )
    {
    }

    public function getChecksum(): string
    {
        return $this->checksum;
    }

    /** Stored file name (basename), e.g. `ab89d3…c9.png`. */
    public function getFilename(): string
    {
        return $this->filename;
    }

    /** Path of the stored file relative to the storage base directory. */
    public function getRelativePath(): string
    {
        return ImagePathLayout::relativePath($this->checksum, $this->type->getFileExtension());
    }

    public function getWidth(): int
    {
        return $this->width;
    }

    public function getHeight(): int
    {
        return $this->height;
    }

    public function getFileSize(): int
    {
        return $this->fileSize;
    }

    public function getType(): ImageType
    {
        return $this->type;
    }
}
