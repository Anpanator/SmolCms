<?php

declare(strict_types=1);

namespace SmolCms\Service\Validation;

use SmolCms\Data\Constant\HttpStatus;
use SmolCms\Data\Request\Request;

readonly class ImageUploadValidator
{
    /**
     * Validates the uploaded file and returns the response status for the outcome:
     * OK when the upload can be processed, otherwise the most specific error status.
     * The PHP upload `error` code decides over generic structural failures, so an
     * oversized upload surfaces as PAYLOAD_TOO_LARGE instead of an anonymous BAD_REQUEST.
     */
    public function getStatus(Request $request): HttpStatus
    {
        if ($request->files === null || empty($request->files['image'])) {
            return HttpStatus::BAD_REQUEST;
        }

        $file = $request->files['image'];
        if (!is_array($file) || !isset($file['tmp_name']) || !isset($file['name'])) {
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

        return HttpStatus::OK;
    }

    public function validate(Request $request): bool
    {
        return $this->getStatus($request) === HttpStatus::OK;
    }
}
