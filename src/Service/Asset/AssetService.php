<?php

declare(strict_types=1);

namespace SmolCms\Service\Asset;

use SmolCms\Service\File\FileSystemAccessService;

readonly class AssetService
{
    private const string HTML_SHELL_FILE = ROOT_DIR . '/private/assets/index.html';

    public function __construct(
        private FileSystemAccessService $fileSystemAccessService,
    )
    {
    }

    public function getHtmlShell(): string
    {
        return $this->fileSystemAccessService->readFile(self::HTML_SHELL_FILE);
    }
}
