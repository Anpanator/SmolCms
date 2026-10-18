<?php

declare(strict_types=1);

namespace SmolCms\Service\Core\CliCommand;

use SmolCms\Data\Constant\CliCommandFlag;
use SmolCms\Service\Asset\JavaScriptIntegrityService;
use SmolCms\Service\File\FileSystemAccessService;

final readonly class GenerateJsIntegrityExecutor implements CliCommandExecutor
{
    private const string HTML_ASSET_FILE = ROOT_DIR . '/private/assets/index.html';

    public function __construct(
        private FileSystemAccessService    $fileSystemAccessService,
        private JavaScriptIntegrityService $javaScriptIntegrityService,
    )
    {
    }

    public function handles(): CliCommandFlag
    {
        return CliCommandFlag::GENERATE_JS_INTEGRITY;
    }

    public function helptext(): string
    {
        return 'Generate SHA-384 integrity attributes for local JavaScript in the HTML shell.';
    }

    public function execute(array $arguments, bool $noconfirm = false): void
    {
        $source = $this->fileSystemAccessService->readFile(self::HTML_ASSET_FILE);
        $result = $this->javaScriptIntegrityService->addIntegrityAttributes($source);

        if ($result['scriptCount'] === 0) {
            echo "No local JavaScript script tags found in private/assets/index.html.\n";
            return;
        }

        $this->fileSystemAccessService->writeFile(self::HTML_ASSET_FILE, $result['html']);
        $tagLabel = $result['scriptCount'] === 1 ? 'tag' : 'tags';
        echo "Updated {$result['scriptCount']} JavaScript script $tagLabel with SHA-384 integrity attributes.\n";
    }
}
