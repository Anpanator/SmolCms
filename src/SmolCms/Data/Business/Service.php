<?php

declare(strict_types=1);

namespace SmolCms\Data\Business;


use InvalidArgumentException;

final class Service
{
    /**
     * Service constructor.
     * @param string $identifier
     * @param string|null $class Optional if the identifier is the fully qualified classname.
     * @param array $parameters The parameters that should be passed into the class constructor.
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

    /**
     * @return string
     */
    public function getIdentifier(): string
    {
        return $this->identifier;
    }

    /**
     * @return string
     */
    public function getClass(): string
    {
        return $this->class;
    }

    /**
     * @return array
     */
    public function getParameters(): array
    {
        return $this->parameters;
    }
}