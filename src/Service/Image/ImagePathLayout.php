<?php
declare(strict_types=1);

namespace SmolCms\Service\Image;

use InvalidArgumentException;

/**
 * Single source of truth for the on-disk image file naming contract:
 *
 * Every stored image file is named `<sha-256 hex checksum>.<extension>` and lives
 * under a two-character subdirectory (the first two characters of the checksum)
 * of its configured base directory, i.e. `<base>/<c[0]><c[1]>/<checksum>.<extension>`.
 *
 * The same layout applies to every image variant directory (original uploads,
 * thumbs, samples, downloads) so that all image files can be located without a
 * database lookup.
 */
final class ImagePathLayout
{
    public const SUBDIR_PREFIX_LENGTH = 2;

    private function __construct()
    {
    }

    /**
     * Stored file name (basename) for the given content checksum and extension, e.g.
     * `ab89d3…c9.png`.
     */
    public static function fileName(string $checksum, string $extension): string
    {
        self::assertChecksum($checksum);
        self::assertExtension($extension);
        return $checksum . '.' . $extension;
    }

    /**
     * Path of a stored file relative to its base directory, e.g. `ab/ab89d3…c9.png`.
     */
    public static function relativePath(string $checksum, string $extension): string
    {
        $subdir = substr($checksum, 0, self::SUBDIR_PREFIX_LENGTH);
        return $subdir . '/' . self::fileName($checksum, $extension);
    }

    /**
     * Path of a stored file relative to its base directory, derived from the full
     * stored file name (e.g. `ab89d3…c9.png` → `ab/ab89d3…c9.png`).
     *
     * Use this variant when only the complete file name is known (e.g. a file name
     * recorded in the database); it must still be a plain `checksum.extension` basename.
     */
    public static function relativePathForFileName(string $fileName): string
    {
        if (str_contains($fileName, '/') || str_contains($fileName, '\\')) {
            throw new InvalidArgumentException('File name must be a basename without path separators.');
        }
        if (strlen($fileName) <= self::SUBDIR_PREFIX_LENGTH) {
            throw new InvalidArgumentException(
                'File name must be at least ' . (self::SUBDIR_PREFIX_LENGTH + 1) . ' characters long.'
            );
        }
        $subdir = substr($fileName, 0, self::SUBDIR_PREFIX_LENGTH);
        return $subdir . '/' . $fileName;
    }

    private static function assertChecksum(string $checksum): void
    {
        if (strlen($checksum) < self::SUBDIR_PREFIX_LENGTH) {
            throw new InvalidArgumentException(
                'Checksum must be at least ' . self::SUBDIR_PREFIX_LENGTH . ' characters long.'
            );
        }
    }

    private static function assertExtension(string $extension): void
    {
        if ($extension === '') {
            throw new InvalidArgumentException('Extension must not be empty.');
        }
    }
}
