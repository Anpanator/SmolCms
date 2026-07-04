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
     * @return array The files (excluding symlinks pointing to not allowed paths)
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
            $normalizedFile = realpath("$resolvedPath/$file");

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

    private function isPathAllowed(string $path): bool
    {
        $normalizedPath = realpath($path);
        if (!$normalizedPath) {
            throw new FileAccessException("Could not resolve path '$path'.");
        }
        return array_any($this->resolvedAllowedDirectories, fn($allowedDir) => str_starts_with($normalizedPath, $allowedDir));
    }

    private function throwNotAllowed(string $path)
    {
        throw new FileAccessException("Access to path '$path' is not allowed.");
    }
}
