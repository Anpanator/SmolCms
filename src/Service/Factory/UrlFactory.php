<?php

declare(strict_types=1);

namespace SmolCms\Service\Factory;


use InvalidArgumentException;
use SmolCms\Data\Business\Url;
use SmolCms\Service\Validation\Validator;

class UrlFactory
{
    /**
     * UrlFactory constructor.
     * @param Validator $validator
     */
    public function __construct(
        private Validator $validator
    ) {
    }

    /**
     * @param string $url
     * @return Url
     * @throws InvalidArgumentException
     */
    public function createUrlFromUrlString(string $url): Url
    {
        $urlParts = parse_url($url);
        $urlObj = new Url(
            protocol: $urlParts['scheme'] ?? '',
            host: $urlParts['host'] ?? '',
            port: $urlParts['port'] ?? null,
            path: $urlParts['path'] ?? '',
            query: $urlParts['query'] ?? '',
        );

        $validationResult = $this->validator->validate($urlObj);
        if (!$validationResult->isValid()) {
            throw new InvalidArgumentException($validationResult->getMessagesAsString());
        }

        return $urlObj;
    }
}