<?php

declare(strict_types=1);

namespace SmolCms\Service\Validation;


use ReflectionAttribute;
use ReflectionObject;
use SmolCms\Data\ValidationResult;
use SmolCms\Service\Validation\Attribute\PropertyValidationAttribute;
use SmolCms\Service\Validation\Attribute\ValidateObject;
use SmolCms\Service\Validation\Attribute\ValidationAttribute;

#@refactor!
class Validator
{
    public function validate(object $objectToValidate): ValidationResult
    {
        $isValid = true;
        $messages = [];
        $refObject = new ReflectionObject($objectToValidate);
        foreach ($refObject->getProperties() as $reflectionProperty) {
            $reflectionProperty->setAccessible(true);
            $propertyValue = $reflectionProperty->getValue($objectToValidate);

            $reflectionAttributes = $reflectionProperty->getAttributes(
                name: ValidationAttribute::class,
                flags: ReflectionAttribute::IS_INSTANCEOF
            );

            foreach ($reflectionAttributes as $reflectionAttribute) {
                $attribute = $reflectionAttribute->newInstance();
                if ($attribute instanceof ValidateObject) {
                    if ($propertyValue === null) {
                        $messages[$reflectionProperty->getName()] = 'Cannot run nested validation on null';
                    }
                    $nestedValidationResult = $this->validate($propertyValue);
                    $isPropertyValid = $nestedValidationResult->isValid();
                    $isValid = $isValid && $isPropertyValid;
                    if (!$isPropertyValid) {
                        $messages[$reflectionProperty->getName()] = $nestedValidationResult->getMessages();
                    }
                } elseif ($attribute instanceof PropertyValidationAttribute) {
                    $isPropertyValid = $attribute->validate($propertyValue);
                    $isValid = $isValid && $isPropertyValid;
                    if (!$isPropertyValid) {
                        $messages[$reflectionProperty->getName()] =
                            'Failed validation on ' . $attribute::class
                            . ' value was: ' . print_r($propertyValue, true);
                    }
                }
            }
        }

        return new ValidationResult(isValid: $isValid, messages: $messages);
    }
}