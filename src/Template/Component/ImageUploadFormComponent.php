<?php
declare(strict_types=1);

namespace SmolCms\Template\Component;

use SmolCms\Data\Constant\HttpMethod;
use SmolCms\Data\Constant\RouteEnum;
use SmolCms\Template\Template;

readonly class ImageUploadFormComponent implements Template
{

    public function __construct(
        private RouteEnum  $route,
        private HttpMethod $method,
        private string     $accept = 'image/*',
        private ?string    $currentImageUrl = null,
    )
    {
    }

    public function render(): string
    {
        $currentImageHtml = $this->currentImageUrl !== null ? "<img src=\"{$this->currentImageUrl}\" alt=\"Current image\" style=\"max-width: 200px; max-height: 200px;\">" : '';
        return <<<HTML
        <form method="{$this->method->value}" action="{$this->route->value}" enctype="multipart/form-data">
            {$currentImageHtml}
            <label for="image">Image</label>
            <input type="file" id="image" name="image" accept="{$this->accept}" required>
            <button type="submit">Upload</button>
        </form>
        HTML;
    }
}
