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
        private HttpStatus $status = HttpStatus::OK,
        private ?string    $content = null,
    ) {
    }

    public function getStatus(): HttpStatus
    {
        return $this->status;
    }

    public function setStatus(HttpStatus $status): void
    {
        $this->status = $status;
    }

    public function getContent(): ?string
    {
        return $this->content;
    }

    public function setContent(?string $content): void
    {
        $this->content = $content;
    }
}