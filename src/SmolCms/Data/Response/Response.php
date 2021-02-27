<?php

declare(strict_types=1);

namespace SmolCms\Data\Response;


use SmolCms\Data\Constant\HttpStatus;

class Response
{

    /**
     * Response constructor.
     */
    public function __construct(
        private int $status = HttpStatus::OK,
        private ?string $content = null,
    ) {
    }

    /**
     * @return int
     */
    public function getStatus(): int
    {
        return $this->status;
    }

    /**
     * @param int $status
     */
    public function setStatus(int $status): void
    {
        $this->status = $status;
    }

    /**
     * @return string|null
     */
    public function getContent(): ?string
    {
        return $this->content;
    }

    /**
     * @param string|null $content
     */
    public function setContent(?string $content): void
    {
        $this->content = $content;
    }
}