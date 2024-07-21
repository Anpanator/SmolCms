<?php
declare(strict_types=1);

namespace SmolCms\Service\Core;


use SmolCms\Config\Templates\TemplateConfig;
use SmolCms\Data\Constant\HttpStatus;
use SmolCms\Data\Response\Response;
use SmolCms\Exception\TemplateException;
use SmolCms\Template\Template;

class TemplateService
{
    public function generateResponse(TemplateConfig $config): Response
    {
        /** Expected config structure:
         * [
         * Template Class (key) => [parameter list]
         * ]
         *
         * The parameter list itself can either contain scalar values
         * or another template class reference as a key => value pair like above
         */

        $templateConfig = $config->getConfig();
        $templateClass = array_key_first($templateConfig);
        $template = $this->buildTemplateFromConfig($templateClass, $templateConfig[$templateClass]);
        return new Response(HttpStatus::OK, $template->render());
    }

    private function buildTemplateFromConfig(string $templateClass, array $config): Template
    {
        $constructorParams = [];
        foreach ($config as $key => $value) {
            if (is_string($key) && class_exists($key)) {
                // $key is a class, hence its value must be an array of parameters.
                if (!is_array($value)) {
                    throw new TemplateException("Found $key in template structure, but associated value is not an array of parameters.");
                }
                $buildParam = $this->buildTemplateFromConfig($key, $value);
            } else {
                // $key is not a class, hence the value should just be passed to the constructor
                $buildParam = $value;
            }
            $constructorParams[] = $buildParam;
        }
        return new $templateClass(...$constructorParams);
    }
}