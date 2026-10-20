<?php

declare(strict_types=1);

namespace SmolCms\Service\Validation;

use SmolCms\Data\Constant\HttpStatus;
use SmolCms\Data\Request\Request;
use SmolCms\Service\Image\ImageDimensions;

readonly class ImageUploadValidator
{
    private const MAX_FILES = 10;

    /**
     * Validates the uploaded files and returns the response status for the outcome:
     * OK when the upload can be processed, otherwise the most specific error status.
     * The PHP upload `error` code decides over generic structural failures, so an
     * oversized upload surfaces as PAYLOAD_TOO_LARGE instead of an anonymous BAD_REQUEST.
     */
    public function getStatus(Request $request): HttpStatus
    {
        $files = $this->getFiles($request);
        if ($files === [] || count($files) > self::MAX_FILES) {
            return HttpStatus::BAD_REQUEST;
        }

        foreach ($files as $file) {
            if (!isset($file['tmp_name'], $file['name'])
                || !is_string($file['tmp_name'])
                || !is_string($file['name'])
            ) {
                return HttpStatus::BAD_REQUEST;
            }

            $uploadError = is_int($file['error'] ?? null) ? $file['error'] : null;
            $status = match ($uploadError) {
                UPLOAD_ERR_OK => HttpStatus::OK,
                UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => HttpStatus::PAYLOAD_TOO_LARGE,
                UPLOAD_ERR_NO_FILE, UPLOAD_ERR_PARTIAL => HttpStatus::BAD_REQUEST,
                UPLOAD_ERR_NO_TMP_DIR, UPLOAD_ERR_CANT_WRITE, UPLOAD_ERR_EXTENSION => HttpStatus::INTERNAL_SERVER_ERROR,
                default => HttpStatus::BAD_REQUEST,
            };
            if ($status !== HttpStatus::OK) {
                return $status;
            }

            // Only accept files that were actually uploaded through the SAPI
            if (!is_uploaded_file($file['tmp_name'])) {
                return HttpStatus::BAD_REQUEST;
            }
            if (ImageDimensions::fromFile($file['tmp_name']) === null) {
                return HttpStatus::BAD_REQUEST;
            }
        }

        return HttpStatus::OK;
    }

    /**
     * Returns upload entries in a per-file shape, normalizing PHP's column-oriented
     * `$_FILES['image']` arrays for multiple file inputs.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getFiles(Request $request): array
    {
        if ($request->files === null || !array_key_exists('image', $request->files)) {
            return [];
        }

        return $this->normalizeFiles($request->files['image']) ?? [];
    }

    /**
     * @return array<int, array<string, mixed>>|null
     */
    private function normalizeFiles(mixed $upload): ?array
    {
        if (!is_array($upload) || !array_key_exists('name', $upload)) {
            return null;
        }

        if (!is_array($upload['name'])) {
            return [$upload];
        }

        $nameKeys = array_keys($upload['name']);
        foreach (['tmp_name', 'type', 'error', 'size'] as $column) {
            if (!array_key_exists($column, $upload)) {
                if ($column === 'tmp_name') {
                    return null;
                }
                continue;
            }
            if (!is_array($upload[$column]) || array_keys($upload[$column]) !== $nameKeys) {
                return null;
            }
        }

        $files = [];
        foreach ($upload['name'] as $key => $name) {
            $file = [
                'name' => $name,
                'tmp_name' => $upload['tmp_name'][$key],
            ];
            foreach (['type', 'error', 'size'] as $column) {
                if (array_key_exists($column, $upload)) {
                    $file[$column] = $upload[$column][$key];
                }
            }
            $files[] = $file;
        }

        return $files;
    }

    public function validate(Request $request): bool
    {
        return $this->getStatus($request) === HttpStatus::OK;
    }
}
