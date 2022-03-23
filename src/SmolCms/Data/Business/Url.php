<?php

declare(strict_types=1);

namespace SmolCms\Data\Business;


use SmolCms\Service\Validation\Attribute\ValidateAllowList;
use SmolCms\Service\Validation\Attribute\ValidateHost;
use SmolCms\Service\Validation\Attribute\ValidateRange;

class Url
{
    public function __construct(
        #[ValidateAllowList(['http', 'https'])]
        public readonly string $protocol,
        #[ValidateHost(ValidateHost::IPV4 | ValidateHost::IPV6 | ValidateHost::DOMAIN)]
        public readonly string $host,
        #[ValidateRange(min: 1, max: 65535)]
        public readonly ?int $port = null,
        public readonly ?string $path = null,
        public readonly ?string $query = null,
    )
    {
    }

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