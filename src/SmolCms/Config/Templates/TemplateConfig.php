<?php
declare(strict_types=1);

namespace SmolCms\Config\Templates;

interface TemplateConfig
{
    /**
     * @return array Return a tree of Templates with their corresponding constructor parameters.
     * Expected structure:
     * [
     *      Template.class => [
     *          FirstTemplateParam.class => ['PlainValue1', 'PlainValue2']
     *      ]
     * ]
     */
    public function getConfig(): array;
}