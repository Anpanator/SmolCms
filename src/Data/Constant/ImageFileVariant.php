<?php

declare(strict_types=1);

namespace SmolCms\Data\Constant;

enum ImageFileVariant
{
    case ORIGINAL;
    case THUMB;
    case SAMPLE;
    case DOWNLOAD;
}
