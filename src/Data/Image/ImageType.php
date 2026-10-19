<?php

declare(strict_types=1);

namespace SmolCms\Data\Image;

enum ImageType:
string
{
    case PNG = 'png';
    case JPEG = 'jpeg';
    case AVIF = 'avif';

    public function getValue(): string
    {
        return $this->value;
    }

    /**
     * Canonical file extension for the type.
     *
     * The ImageType backing value itself is used as the extension (so `jpeg`, NOT `jpg`).
     * Every stored image file is named `<sha-256 hex checksum>.<extension>` — see ImagePathLayout.
     */
    public function getFileExtension(): string
    {
        return $this->value;
    }
}
