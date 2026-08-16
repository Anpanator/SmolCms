<?php

declare(strict_types=1);

namespace SmolCms\Service\Validation;

use SmolCms\Data\Request\Request;

readonly class ImageUploadValidator
{
    public function validate(Request $request): bool
    {
        // Check if files were uploaded
        if ($request->files === null || empty($request->files['image'])) {
            return false;
        }

        $file = $request->files['image'];

        // Validate single file upload
        if (!is_array($file) || !isset($file['tmp_name']) || !isset($file['name'])) {
            return false;
        }

        $tmpPath = $file['tmp_name'];

        // Check if file was uploaded successfully
        if (!is_uploaded_file($tmpPath)) {
            return false;
        }

        return true;
    }
}
