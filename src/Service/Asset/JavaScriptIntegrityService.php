<?php

declare(strict_types=1);

namespace SmolCms\Service\Asset;

use RuntimeException;
use SmolCms\Service\File\FileSystemAccessService;

readonly class JavaScriptIntegrityService
{
    private const string JAVASCRIPT_DIRECTORY = ROOT_DIR . '/public/js';
    private const string JAVASCRIPT_URL_PREFIX = '/public/js/';

    private const string SCRIPT_TAG_PATTERN = '~<script\b[^>]*>~i';
    private const string SCRIPT_SOURCE_PATTERN = '~(?:^|\s)src\s*=\s*(?:"(?<double>[^"]*)"|\'(?<single>[^\']*)\'|(?<unquoted>[^\s>]+))~i';

    public function __construct(
        private FileSystemAccessService $fileSystemAccessService,
        private string                  $javascriptDirectory = self::JAVASCRIPT_DIRECTORY,
    )
    {
    }

    /**
     * @return array{html: string, scriptCount: int}
     */
    public function addIntegrityAttributes(string $html): array
    {
        $scriptCount = 0;
        $updatedHtml = preg_replace_callback(
            self::SCRIPT_TAG_PATTERN,
            function (array $matches) use (&$scriptCount): string {
                $tag = $matches[0];
                $source = $this->getScriptSource($tag);
                if ($source === null) {
                    return $tag;
                }

                $assetPath = $this->getLocalJavaScriptPath($source);
                if ($assetPath === null) {
                    return $tag;
                }

                $assetContent = $this->fileSystemAccessService->readFile($assetPath);
                $integrity = 'sha384-' . base64_encode(hash('sha384', $assetContent, true));
                $scriptCount++;

                return $this->setAttribute(
                    $this->setAttribute($tag, 'integrity', $integrity),
                    'crossorigin',
                    'anonymous',
                );
            },
            $html,
        );

        if ($updatedHtml === null) {
            throw new RuntimeException('Could not update JavaScript integrity attributes.');
        }

        return [
            'html' => $updatedHtml,
            'scriptCount' => $scriptCount,
        ];
    }

    private function getScriptSource(string $tag): ?string
    {
        if (preg_match(self::SCRIPT_SOURCE_PATTERN, $tag, $matches, PREG_UNMATCHED_AS_NULL) !== 1) {
            return null;
        }

        return $matches['double'] ?? $matches['single'] ?? $matches['unquoted'] ?? null;
    }

    private function getLocalJavaScriptPath(string $source): ?string
    {
        $url = parse_url($source);
        if ($url === false) {
            throw new RuntimeException("Invalid script source URL: $source");
        }

        $path = $url['path'] ?? null;
        if (
            isset($url['scheme'])
            || isset($url['host'])
            || !is_string($path)
            || !str_starts_with($path, self::JAVASCRIPT_URL_PREFIX)
        ) {
            return null;
        }

        $relativePath = rawurldecode(substr($path, strlen(self::JAVASCRIPT_URL_PREFIX)));
        if ($relativePath === '' || str_contains($relativePath, "\0")) {
            throw new RuntimeException("Invalid local JavaScript source URL: $source");
        }

        $javascriptDirectory = realpath($this->javascriptDirectory);
        $assetPath = realpath($this->javascriptDirectory . '/' . $relativePath);
        if (
            $javascriptDirectory === false
            || $assetPath === false
            || !str_starts_with($assetPath, $javascriptDirectory . DIRECTORY_SEPARATOR)
            || !is_file($assetPath)
        ) {
            throw new RuntimeException("JavaScript source must resolve to a file under /public/js: $source");
        }

        return $assetPath;
    }

    private function setAttribute(string $tag, string $attributeName, string $value): string
    {
        $attributePattern = '~\s' . preg_quote($attributeName, '~')
            . '(?:\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s>]+))?~i';
        $escapedValue = htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $replacement = ' ' . $attributeName . '="' . $escapedValue . '"';

        if (preg_match($attributePattern, $tag) === 1) {
            $updatedTag = preg_replace($attributePattern, $replacement, $tag, 1);
        } else {
            $updatedTag = preg_replace('~\s*/?>$~', $replacement . '$0', $tag, 1);
        }

        if ($updatedTag === null) {
            throw new RuntimeException("Could not add the $attributeName script attribute.");
        }

        return $updatedTag;
    }
}
