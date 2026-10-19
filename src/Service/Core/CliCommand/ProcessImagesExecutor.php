<?php

declare(strict_types=1);

namespace SmolCms\Service\Core\CliCommand;

use SmolCms\Data\Constant\CliCommandFlag;
use SmolCms\Service\Image\ImageProcessingService;

final readonly class ProcessImagesExecutor implements CliCommandExecutor
{
    private const int DEFAULT_BATCH_SIZE = 100;

    public function __construct(
        private ImageProcessingService $imageProcessingService,
        private string $lockFilePath,
    ) {
    }

    public function handles(): CliCommandFlag
    {
        return CliCommandFlag::PROCESS_IMAGES;
    }

    public function helptext(): string
    {
        return 'Process pending uploads into thumb/sample/download variants. Usage: --process-images [batch-size]';
    }

    public function execute(array $arguments, bool $noconfirm = false): void
    {
        $batchSize = $this->parseBatchSize($arguments);
        if ($batchSize === null) {
            echo "Usage: --process-images [batch-size]\n";
            echo 'Batch size must be a positive integer; default is ' . self::DEFAULT_BATCH_SIZE . ".\n";
            return;
        }

        $lock = fopen($this->lockFilePath, 'c');
        if ($lock === false) {
            echo "Could not open image processing lock file: {$this->lockFilePath}\n";
            return;
        }

        // Non-blocking exclusive lock so that only one worker processes images at a time.
        if (!flock($lock, LOCK_EX | LOCK_NB)) {
            fclose($lock);
            echo "Another worker is already processing images.\n";
            return;
        }

        try {
            $processed = $this->imageProcessingService->processBatch($batchSize);
            echo "Processed $processed image(s).\n";
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    private function parseBatchSize(array $arguments): ?int
    {
        $raw = $arguments[0] ?? null;
        if ($raw === null) {
            return self::DEFAULT_BATCH_SIZE;
        }
        // Accept only plain positive integer literals ("0" and anything else is invalid).
        if (!is_string($raw) || !ctype_digit($raw) || (int)$raw < 1) {
            return null;
        }
        return (int)$raw;
    }
}
