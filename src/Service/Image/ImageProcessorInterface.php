<?php
declare(strict_types=1);

namespace SmolCms\Service\Image;

interface ImageProcessorInterface
{
    public function process(string $imageData, string $filename): void;
}
