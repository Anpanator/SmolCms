<?php

declare(strict_types=1);

namespace SmolCms\Data\Business;


use SmolCms\Service\Validation\Attribute\ValidateAllowList;
use SmolCms\Service\Validation\Attribute\ValidateHost;
use SmolCms\Service\Validation\Attribute\ValidateRange;

class Url
{

    /**
     * Url constructor.
     * @param string $protocol
     * @param string $host
     * @param int|null $port
     * @param string|null $path
     * @param string|null $query
     */
    public function __construct(
        #[ValidateAllowList(['http', 'https'])]
        private string $protocol,
        #[ValidateHost(ValidateHost::IPV4 | ValidateHost::IPV6 | ValidateHost::DOMAIN)]
        private string $host,
        #[ValidateRange(min: 1, max: 65535)]
        private ?int $port,
        private ?string $path,
        private ?string $query,
    ) {
    }

    /**
     * @return string
     */
    public function getProtocol(): string
    {
        return $this->protocol;
    }

    /**
     * @return string
     */
    public function getHost(): string
    {
        return $this->host;
    }

    /**
     * @return int|null
     */
    public function getPort(): ?int
    {
        return $this->port;
    }

    /**
     * @return string|null
     */
    public function getPath(): ?string
    {
        return $this->path;
    }

    /**
     * @return string|null
     */
    public function getQuery(): ?string
    {
        return $this->query;
    }

    /**
     * @return string
     */
    public function __toString(): string
    {
        $url = $this->protocol ? "{$this->protocol}://" : '';
        $url .= $this->host ?: '';
        $url .= $this->port ? ":{$this->port}" : '';
        $url .= $this->path ?: '/';
        $url .= $this->query ?: '';
        return $url;
    }
}