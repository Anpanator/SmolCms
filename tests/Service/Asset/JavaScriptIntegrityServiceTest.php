<?php

declare(strict_types=1);

namespace SmolCms\Test\Service\Asset;

use PHPUnit\Framework\MockObject\MockObject;
use RuntimeException;
use SmolCms\Service\Asset\JavaScriptIntegrityService;
use SmolCms\Service\File\FileSystemAccessService;
use SmolCms\TestUtils\Attributes\Mock;
use SmolCms\TestUtils\SimpleTestCase;

class JavaScriptIntegrityServiceTest extends SimpleTestCase
{
    private const string TEST_JAVASCRIPT_DIRECTORY = ROOT_DIR . '/tests/TestUtils/TestFiles';
    private const string TEST_JAVASCRIPT_PATH = self::TEST_JAVASCRIPT_DIRECTORY . '/integrity-test.js';
    private const string TEST_JAVASCRIPT_URL = '/public/js/integrity-test.js';

    #[Mock(FileSystemAccessService::class)]
    private FileSystemAccessService|MockObject $fileSystemAccessService;

    private JavaScriptIntegrityService $javaScriptIntegrityService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->javaScriptIntegrityService = new JavaScriptIntegrityService(
            $this->fileSystemAccessService,
            self::TEST_JAVASCRIPT_DIRECTORY,
        );
    }

    public function testAddIntegrityAttributes_addsSha384AndAnonymousCrossorigin(): void
    {
        $assetContent = 'export const current = true;';
        $integrity = 'sha384-' . base64_encode(hash('sha384', $assetContent, true));
        $this->fileSystemAccessService
            ->expects($this->once())
            ->method('readFile')
            ->with(self::TEST_JAVASCRIPT_PATH)
            ->willReturn($assetContent)
            ->seal();

        $result = $this->javaScriptIntegrityService->addIntegrityAttributes(
            '<script type="module" src="' . self::TEST_JAVASCRIPT_URL . '"></script>'
        );

        self::assertSame(1, $result['scriptCount']);
        self::assertSame(
            '<script type="module" src="' . self::TEST_JAVASCRIPT_URL . '" integrity="' . $integrity
            . '" crossorigin="anonymous"></script>',
            $result['html'],
        );
    }

    public function testAddIntegrityAttributes_replacesExistingIntegrityAndCrossorigin(): void
    {
        $assetContent = 'console.log("updated");';
        $integrity = 'sha384-' . base64_encode(hash('sha384', $assetContent, true));
        $this->fileSystemAccessService
            ->expects($this->once())
            ->method('readFile')
            ->with(self::TEST_JAVASCRIPT_PATH)
            ->willReturn($assetContent)
            ->seal();

        $result = $this->javaScriptIntegrityService->addIntegrityAttributes(
            '<script src="' . self::TEST_JAVASCRIPT_URL
            . '" integrity="stale" crossorigin="use-credentials"></script>'
        );

        self::assertSame(1, $result['scriptCount']);
        self::assertSame(
            '<script src="' . self::TEST_JAVASCRIPT_URL . '" integrity="' . $integrity
            . '" crossorigin="anonymous"></script>',
            $result['html'],
        );
    }

    public function testAddIntegrityAttributes_ignoresInlineAndExternalScripts(): void
    {
        $this->fileSystemAccessService
            ->expects($this->never())
            ->method('readFile')
            ->seal();

        $html = '<script>runInline();</script>'
            . '<script src="https://cdn.example/app.js"></script>'
            . '<script data-src="' . self::TEST_JAVASCRIPT_URL . '"></script>';

        $result = $this->javaScriptIntegrityService->addIntegrityAttributes($html);

        self::assertSame(0, $result['scriptCount']);
        self::assertSame($html, $result['html']);
    }

    public function testAddIntegrityAttributes_rejectsPathsOutsideJavaScriptDirectory(): void
    {
        $this->fileSystemAccessService
            ->expects($this->never())
            ->method('readFile')
            ->seal();

        $this->expectException(RuntimeException::class);
        $this->javaScriptIntegrityService->addIntegrityAttributes(
            '<script src="/public/js/../Attributes/Mock.php"></script>'
        );
    }
}
