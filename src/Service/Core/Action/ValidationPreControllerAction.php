<?php

declare(strict_types=1);

namespace SmolCms\Service\Core\Action;

use SmolCms\Data\Request\Request;
use SmolCms\Data\Request\ValidatedRequest;
use SmolCms\Data\Response\Response;
use SmolCms\Exception\BadRequestException;
use SmolCms\Service\Validation\Validator;

final readonly class ValidationPreControllerAction implements PreControllerAction
{
    public function __construct(
        private Validator $validator,
    )
    {
    }

    public function process(Request $request, object $controller, string $handler, array &$handlerArguments): ?Response
    {
        $mappedRequest = $handlerArguments['request'] ?? null;
        if (!$mappedRequest instanceof ValidatedRequest) {
            return null;
        }

        $validationResult = $this->validator->validate($mappedRequest);
        if (!$validationResult->isValid()) {
            $className = $controller::class;
            throw new BadRequestException(
                "Validation failed for request in {$className}::{$handler}"
                . ": {$validationResult->getMessagesAsString()}"
            );
        }
        return null;
    }
}
