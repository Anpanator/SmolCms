<?php

declare(strict_types=1);

namespace SmolCms\Data\Business\Routing;


use InvalidArgumentException;

class Service
{
    /**
     * Service constructor.
     * @param string $identifier
     * @param string|null $class Optional if the identifier is the fully qualified classname
     * @param array $parameters
     */
    public function __construct(
        private string $identifier,
        private ?string $class = null,
        private array $parameters = []
    ) {
        $this->class = $class ?? $identifier;
        if (!class_exists($this->class)) {
            throw new InvalidArgumentException("Class {$this->class} could not be found.");
        }
    }
}