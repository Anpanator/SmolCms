<?php
declare(strict_types=1);

namespace SmolCms\Service\Image;

readonly class ImageProcessorFacade
{


    /**
     * @var ImageProcessorInterface[]
     */
    private array $processors;

    public function __construct(
        ImageProcessorInterface ...$processors
    )
    {
        $this->processors = $processors;
    }

    public function processImage(string $imageData, string $filename): void
    {
        foreach ($this->processors as $processor) {
            $processor->process($imageData, $filename);
        }
    }
}
