<?php

declare(strict_types=1);

namespace SmolCms\Data;


class ValidationResult
{

    /**
     * ValidationResult constructor.
     * @param bool $isValid
     * @param array $messages
     */
    public function __construct(
        private bool $isValid,
        private array $messages = [],
    ) {
    }

    /**
     * @return bool
     */
    public function isValid(): bool
    {
        return $this->isValid;
    }

    /**
     * @return array
     */
    public function getMessages(): array
    {
        return $this->messages;
    }

    /**
     * @return string
     */
    public function getMessagesAsString(): string
    {
        return print_r($this->messages, true);
    }
}