<?php

declare(strict_types=1);

namespace SmolCms\Test\Service\Core\CliCommand;

use PHPUnit\Framework\MockObject\MockObject;
use SmolCms\Service\Asset\JavaScriptIntegrityService;
use SmolCms\Service\Core\CliCommand\GenerateJsIntegrityExecutor;
use SmolCms\Service\File\FileSystemAccessService;
use SmolCms\TestUtils\Attributes\Mock;
use SmolCms\TestUtils\SimpleTestCase;

class GenerateJsIntegrityExecutorTest extends SimpleTestCase
{
    private const string HTML_ASSET_PATH = ROOT_DIR . '/private/assets/index.html';
    private const string TEST_FILES_DIRECTORY = ROOT_DIR . '/tests/TestUtils/TestFiles';
    private const string TEST_HTML_PATH = self::TEST_FILES_DIRECTORY . '/integrity-test.html';
    private const string TEST_JAVASCRIPT_PATH = self::TEST_FILES_DIRECTORY . '/integrity-test.js';

    #[Mock(FileSystemAccessService::class)]
    private FileSystemAccessService|MockObject $fileSystemAccessService;

    private GenerateJsIntegrityExecutor $executor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->executor = new GenerateJsIntegrityExecutor(
            $this->fileSystemAccessService,
            new JavaScriptIntegrityService($this->fileSystemAccessService, self::TEST_FILES_DIRECTORY),
        );
    }

    public function testExecute_updatesHtmlAssetWithFreshIntegrityAttributes(): void
    {
        $source = file_get_contents(self::TEST_HTML_PATH);
        self::assertIsString($source);
        $javascript = file_get_contents(self::TEST_JAVASCRIPT_PATH);
        self::assertIsString($javascript);

        $scriptTag = '<script type="module" src="/public/js/integrity-test.js"></script>';
        $integrity = 'sha384-' . base64_encode(hash('sha384', $javascript, true));
        $updatedSource = str_replace(
            $scriptTag,
            '<script type="module" src="/public/js/integrity-test.js" integrity="' . $integrity
            . '" crossorigin="anonymous"></script>',
            $source,
        );

        $this->fileSystemAccessService
            ->expects($this->exactly(2))
            ->method('readFile')
            ->willReturnMap([
                [self::HTML_ASSET_PATH, $source],
                [self::TEST_JAVASCRIPT_PATH, $javascript],
            ]);
        $this->fileSystemAccessService
            ->expects($this->once())
            ->method('writeFile')
            ->with(self::HTML_ASSET_PATH, $updatedSource)
            ->seal();

        $this->expectOutputString(
            "Updated 1 JavaScript script tag with SHA-384 integrity attributes.\n"
        );
        $this->executor->execute([]);
    }
}
