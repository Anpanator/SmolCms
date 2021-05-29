<?php
declare(strict_types=1);

namespace SmolCms\Data\Response;


class TemplateMetadata
{

    /**
     * TemplateMetadata constructor.
     * @param string $title
     * @param string $language
     */
    public function __construct(
        private string $title = '',
        private string $language = ''
    )
    {
    }

    /**
     * @return string
     */
    public function getTitle(): string
    {
        return $this->title;
    }

    /**
     * @return string
     */
    public function getLanguage(): string
    {
        return $this->language;
    }
}