<?php

declare(strict_types=1);

namespace SmolCms\Service\File;

use RuntimeException;
use SmolCms\Exception\FileAccessException;

readonly class FileSystemAccessService
{
    private array $resolvedAllowedDirectories;

    public function __construct(string ...$allowedDirectories)
    {
        $allowedDirs = [];
        foreach ($allowedDirectories as $unresolvedAllowedDir) {
            $allowedDirs[] = realpath($unresolvedAllowedDir)
                ?: throw new RuntimeException("Allowed directory $unresolvedAllowedDir does not exist. Check config!");
        }
        $this->resolvedAllowedDirectories = $allowedDirs;
    }


    public function readFile(string $path): string
    {
        $resolvedPath = realpath($path);
        if ($resolvedPath === false) {
            throw new FileAccessException("Could not read file at path '$path'.");
        }
        $this->isPathAllowed($resolvedPath) ?: $this->throwNotAllowed($path);
        if (!is_file($resolvedPath) || !is_readable($resolvedPath)) {
            throw new FileAccessException("Could not read file at path '$path'.");
        }
        return file_get_contents($resolvedPath);
    }

    public function fileExists(string $path): bool
    {
        $resolvedDir = realpath(dirname($path));
        if ($resolvedDir === false) {
            throw new FileAccessException("Could not resolve directory path '$path'.");
        }
        $this->isPathAllowed($resolvedDir) ?: $this->throwNotAllowed($path);
        $resolvedPath = realpath($path);
        if (!$resolvedPath) {
            return false;
        }
        $this->isPathAllowed($resolvedPath) ?: $this->throwNotAllowed($path);
        return is_file($resolvedPath);
    }

    public function writeFile(string $path, string $content): void
    {
        $resolvedDir = realpath(dirname($path));
        $filename = basename($path);
        if ($resolvedDir === false) {
            throw new FileAccessException("Could not resolve directory for path '$path'.");
        }
        $this->isPathAllowed($resolvedDir) ?: $this->throwNotAllowed($path);
        if (is_link($path)) {
            throw new FileAccessException("Refusing to write through symlink at path '$path'.");
        }
        $resolvedPath = realpath($path);
        if ($resolvedPath !== false) {
            $this->isPathAllowed($resolvedPath) ?: $this->throwNotAllowed($path);
        }
        if (file_put_contents("$resolvedDir/$filename", $content) === false) {
            throw new FileAccessException("Could not write file at path '$path'.");
        }
    }

    /**
     * Ensures a directory exists directly beneath an existing allowed directory.
     * A symbolic link at the requested path is rejected.
     */
    public function ensureDirectory(string $path): void
    {
        $directoryName = basename($path);
        if ($directoryName === '' || $directoryName === '.' || $directoryName === '..') {
            throw new FileAccessException("Invalid directory path '$path'.");
        }

        if (is_link($path)) {
            throw new FileAccessException("Refusing to follow symlink at directory path '$path'.");
        }

        $existingPath = realpath($path);
        if ($existingPath !== false) {
            $this->isPathAllowed($existingPath) ?: $this->throwNotAllowed($path);
            if (!is_dir($existingPath)) {
                throw new FileAccessException("Path '$path' is not a directory.");
            }
            return;
        }

        $resolvedParent = realpath(dirname($path));
        if ($resolvedParent === false) {
            $this->ensureDirectory(dirname($path));
            $resolvedParent = realpath(dirname($path));
        }
        if ($resolvedParent === false || !is_dir($resolvedParent)) {
            throw new FileAccessException("Could not resolve parent directory for path '$path'.");
        }
        $this->isPathAllowed($resolvedParent) ?: $this->throwNotAllowed($path);
        $resolvedPath = $resolvedParent . DIRECTORY_SEPARATOR . $directoryName;

        if (!mkdir($resolvedPath, 0777)) {
            // Another request may have created it after the initial check.
            if (is_link($resolvedPath)) {
                throw new FileAccessException("Refusing to follow symlink at directory path '$path'.");
            }
            $existingPath = realpath($resolvedPath);
            if ($existingPath === false || !is_dir($existingPath)) {
                throw new FileAccessException("Could not create directory at path '$path'.");
            }
        }

        $resolvedPath = realpath($resolvedPath);
        if ($resolvedPath === false) {
            throw new FileAccessException("Could not resolve directory path '$path'.");
        }
        $this->isPathAllowed($resolvedPath) ?: $this->throwNotAllowed($path);
        if (!is_dir($resolvedPath)) {
            throw new FileAccessException("Path '$path' is not a directory.");
        }
    }

    public function deleteFile(string $path): void
    {
        $resolvedPath = realpath($path);
        if ($resolvedPath === false) {
            throw new FileAccessException("File at path '$path' does not exist.");
        }
        $this->isPathAllowed($resolvedPath) ?: $this->throwNotAllowed($path);
        if (!unlink($resolvedPath)) {
            throw new FileAccessException("Could not delete file at path '$path'.");
        }
    }

    /**
     * @param string $path
     * @return array The regular files in the directory; symbolic links are excluded.
     */
    public function listFiles(string $path): array
    {
        $resolvedPath = realpath($path);
        if ($resolvedPath === false) {
            throw new FileAccessException("Could not list files at path '$path'.");
        }
        $this->isPathAllowed($resolvedPath) ?: $this->throwNotAllowed($path);
        if (!is_dir($resolvedPath)) {
            throw new FileAccessException("'$path' is not a directory.");
        }
        $files = scandir($resolvedPath, SCANDIR_SORT_ASCENDING);
        if ($files === false) {
            throw new FileAccessException("Could not list files at path '$path'.");
        }
        $resultFiles = [];
        foreach ($files as $file) {
            $filePath = "$resolvedPath/$file";
            if (is_link($filePath)) {
                continue;
            }
            $normalizedFile = realpath($filePath);

            if ($normalizedFile === false) {
                continue;
            }
            if (!$this->isPathAllowed($normalizedFile) || !is_file($normalizedFile)) {
                continue;
            }
            $resultFiles[] = $file;
        }
        return $resultFiles;
    }

    public function isPathAllowed(string $path): bool
    {
        $normalizedPath = realpath($path);
        if (!$normalizedPath) {
            throw new FileAccessException("Could not resolve path '$path'.");
        }
        return array_any(
            $this->resolvedAllowedDirectories,
            fn($allowedDir) => $normalizedPath === $allowedDir
                || str_starts_with($normalizedPath, $allowedDir . DIRECTORY_SEPARATOR)
        );
    }

    private function throwNotAllowed(string $path)
    {
        throw new FileAccessException("Access to path '$path' is not allowed.");
    }
}
