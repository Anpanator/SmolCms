<?php
declare(strict_types=1);

namespace SmolCms\Test\Service\Image;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use SmolCms\Service\Image\ImagePathLayout;
use SmolCms\TestUtils\SimpleTestCase;

class ImagePathLayoutTest extends SimpleTestCase
{
    private const string CHECKSUM = 'ab89d3c4d1a2b3c4d5e6f7a8b9c0d1e2f3a4b5c6d7e8f9a0b1c2d3e4f5a6b7c8';

    public function testFileName_buildsChecksumExtensionName(): void
    {
        self::assertSame(self::CHECKSUM . '.png', ImagePathLayout::fileName(self::CHECKSUM, 'png'));
    }

    public function testRelativePath_prefixesTwoCharSubdirectory(): void
    {
        self::assertSame(
            'ab/' . self::CHECKSUM . '.jpeg',
            ImagePathLayout::relativePath(self::CHECKSUM, 'jpeg')
        );
    }

    public function testFileName_shortChecksum_throwsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains('Checksum must be at least 2 characters long.');

        ImagePathLayout::fileName('a', 'png');
    }

    public function testFileName_emptyExtension_throwsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains('Extension must not be empty.');

        ImagePathLayout::fileName(self::CHECKSUM, '');
    }

    public static function pathSeparatorProvider(): array
    {
        return [
            'forward slash' => ['../' . self::CHECKSUM . '.png'],
            'backslash' => ['..\\' . self::CHECKSUM . '.png'],
        ];
    }

    #[DataProvider('pathSeparatorProvider')]
    public function testRelativePathForFileName_rejectsPathSeparators(string $fileName): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains('File name must be a basename without path separators.');

        ImagePathLayout::relativePathForFileName($fileName);
    }
}
