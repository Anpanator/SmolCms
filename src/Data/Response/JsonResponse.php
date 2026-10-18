<?php

declare(strict_types=1);

namespace SmolCms\Data\Response;

use JsonException;
use SmolCms\Data\Constant\HttpHeader;
use SmolCms\Data\Constant\HttpStatus;
use SmolCms\Exception\InvalidStateException;

class JsonResponse extends Response
{
    /**
     * @param mixed $data Data to be JSON-encoded, or null to send a response without content (e.g. 204).
     */
    public function __construct(mixed $data = null, HttpStatus $status = HttpStatus::OK)
    {
        parent::__construct(status: $status);
        $this->addHeader(HttpHeader::CONTENT_TYPE->value, 'application/json');
        if ($data === null) {
            return;
        }
        try {
            $this->setContent(json_encode($data, JSON_THROW_ON_ERROR));
        } catch (JsonException $e) {
            throw new InvalidStateException('Could not encode response data to JSON.', 0, $e);
        }
    }
}
