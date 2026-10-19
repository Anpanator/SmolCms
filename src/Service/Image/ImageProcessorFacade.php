<?php
declare(strict_types=1);

namespace SmolCms\Service\Image;

use GdImage;
use InvalidArgumentException;
use SmolCms\Data\Image\ImageType;

/**
 * Validates uploaded image data by decoding it through the processor matching the
 * detected image type. Uploads retain their original bytes; the decoded GdImage is
 * used to confirm the image is readable and capture its dimensions.
 */
readonly class ImageProcessorFacade
{
    /**
     * @var array<string, ImageProcessorInterface>
     */
    private array $processorsByType;

    public function __construct(
        ImageProcessorInterface ...$processors
    )
    {
        $processorsByType = [];
        foreach ($processors as $processor) {
            $typeValue = $processor->getType()->value;
            if (isset($processorsByType[$typeValue])) {
                throw new InvalidArgumentException("Duplicate image processor for type: $typeValue");
            }
            $processorsByType[$typeValue] = $processor;
        }
        $this->processorsByType = $processorsByType;
    }

    public function sanitizeImage(string $imageData, ImageType $type): GdImage
    {
        $processor = $this->processorsByType[$type->value]
            ?? throw new InvalidArgumentException("No image processor registered for type: {$type->value}");

        return $processor->sanitize($imageData);
    }
}
