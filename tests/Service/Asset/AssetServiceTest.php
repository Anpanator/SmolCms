<?php

declare(strict_types=1);

namespace SmolCms\Test\Service\Asset;

use PHPUnit\Framework\MockObject\MockObject;
use SmolCms\Service\Asset\AssetService;
use SmolCms\Service\File\FileSystemAccessService;
use SmolCms\TestUtils\Attributes\Mock;
use SmolCms\TestUtils\SimpleTestCase;

class AssetServiceTest extends SimpleTestCase
{
    private const string TEST_HTML_PATH = ROOT_DIR . '/tests/TestUtils/TestFiles/integrity-test.html';

    #[Mock(FileSystemAccessService::class)]
    private FileSystemAccessService|MockObject $fileSystemAccessService;

    private AssetService $assetService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assetService = new AssetService($this->fileSystemAccessService);
    }

    public function testGetHtmlShell_readsHtmlAsset(): void
    {
        $html = file_get_contents(self::TEST_HTML_PATH);
        self::assertIsString($html);

        $this->fileSystemAccessService
            ->expects($this->once())
            ->method('readFile')
            ->with(ROOT_DIR . '/private/assets/index.html')
            ->willReturn($html)
            ->seal();

        self::assertSame($html, $this->assetService->getHtmlShell());
    }
}
