<?php
declare(strict_types=1);

namespace SmolCms\Service\Core;


use ReflectionClass;
use ReflectionException;
use ReflectionNamedType;
use SmolCms\Data\Constant\HttpStatus;
use SmolCms\Data\Response\Response;
use SmolCms\Exception\TemplateException;
use SmolCms\Template\Template;

class TemplateService
{

    /**
     * TemplateService constructor.
     * @param ServiceBuilder $serviceBuilder
     */
    public function __construct(
        private ServiceBuilder $serviceBuilder
    )
    {
    }

    /**
     * @param string $templateClass
     * @param array $data
     * @return Response
     */
    public function generateResponse(string $templateClass, array $data): Response
    {
        try {
            $template = $this->serviceBuilder->getService($templateClass);
            $requiredDataKeys = $this->getRequiredDataKeys($templateClass);
        } catch (ReflectionException $e) {
            throw new TemplateException("Error while processing template $templateClass", $e);
        }
        $missingDataKeys = $this->getMissingDataKeys($requiredDataKeys, $data);
        if (!empty($missingDataKeys)) {
            throw new TemplateException(
                "Missing data for the template $templateClass! Please add this data: " . implode(',', $missingDataKeys)
            );
        }
        return new Response(HttpStatus::OK, $template->render($data));
    }

    /**
     * @param string[] $required
     * @param array<string, mixed> $data
     * @return string[]
     */
    private function getMissingDataKeys(array $required, array $data): array
    {
        $missingKeys = [];
        foreach ($required as $key) {
            if (!array_key_exists($key, $data)) {
                $missingKeys[] = $key;
            }
        }
        return $missingKeys;
    }

    /**
     * @param string $templateClass
     * @return string[]
     * @throws ReflectionException
     */
    private function getRequiredDataKeys(string $templateClass): array
    {
        // Use array keys for near-constant time conflict lookup
        $refTemplate = new ReflectionClass($templateClass);
        if (!$refTemplate->implementsInterface(Template::class)) {
            throw new TemplateException(
                "$templateClass is not a template class! Make sure it implements the Template interface."
            );
        }

        $requiredDataKeys = array_fill_keys($templateClass::getTemplateVars(), null);
        $refConstructor = $refTemplate->getConstructor();
        if ($refConstructor === null) {
            return array_keys($requiredDataKeys);
        }
        foreach ($refConstructor->getParameters() as $refParam) {
            $paramType = $refParam->getType();
            if (!($paramType instanceof ReflectionNamedType)) {
                // Skip constructor params that aren't type hinted
                continue;
            }
            /** @var ReflectionNamedType $paramType */
            $nestedTemplateClass = $paramType->getName();
            // Skip constructor params that aren't a template
            $refClassOfParameter = new ReflectionClass($nestedTemplateClass);
            if (!$refClassOfParameter->implementsInterface(Template::class)) {
                continue;
            }

            foreach ($this->getRequiredDataKeys($nestedTemplateClass) as $templateVar) {
                if (array_key_exists($templateVar, $requiredDataKeys)) {
                    // TODO: Log warning/debug message if the same variable name is used multiple times
                }

                $requiredDataKeys[$templateVar] = null;
            }

        }
        return array_keys($requiredDataKeys);
    }
}