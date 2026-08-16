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
}
