<?php
declare(strict_types=1);

namespace SmolCms\Service\Image;

use GdImage;
use InvalidArgumentException;
use SmolCms\Data\Image\ImageType;

/**
 * Sanitizes an uploaded image by decoding it through exactly the processor that
 * matches the detected image type. The returned GdImage contains pure pixel data
 * (no EXIF or other metadata); encoding it again as the detected type guarantees
 * the stored file truly is that format.
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
