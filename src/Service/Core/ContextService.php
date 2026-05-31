<?php
declare(strict_types=1);

namespace SmolCms\Service\Core;

use InvalidArgumentException;
use SmolCms\Data\Constant\ContextKey;
use SmolCms\Service\Core\Attribute\StatefulService;

#[StatefulService]
class ContextService
{
    private array $context = [];

    public function setContext(ContextKey $key, mixed $value): void
    {
        if ($value === null) {
            $this->context[$key->name] = null;
            return;
        }
        if (gettype($value) !== $key->value) {
            throw new InvalidArgumentException(
                "Invalid context value for key: {$key->name}. Type mismatch. " .
                "Expected type: {$key->value}, actual type: " . gettype($value) . "."
            );
        }

        $this->context[$key->name] = $value;
    }

    public function getContext(ContextKey $key): mixed
    {
        return $this->context[$key->name] ?? null;
    }
}