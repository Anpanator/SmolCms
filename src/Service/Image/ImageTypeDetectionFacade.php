<?php

declare(strict_types=1);

namespace SmolCms\Service\Image;

use SmolCms\Data\Image\ImageType;
use SmolCms\Data\Image\ImageTypeDetector;

readonly class ImageTypeDetectionFacade
{
    /** @var ImageTypeDetector[] */
    private array $detectors;

    /**
     * @param ImageTypeDetector ...$detectors
     */
    public function __construct(ImageTypeDetector ...$detectors)
    {
        $this->detectors = $detectors;
    }

    /**
     * Detects the image type from file content
     * Returns the detected ImageType enum or null if not recognized
     */
    public function getImageType(string $fileContent): ?ImageType
    {
        foreach ($this->detectors as $detector) {
            $result = $detector->getImageType($fileContent);
            if ($result !== null) {
                return $result;
            }
        }
        return null;
    }
}
