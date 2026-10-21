<?php
declare(strict_types=1);

namespace SmolCms\Service\Core\Startup;

use Closure;
use SmolCms\Data\Constant\HttpHeader;

readonly class SetCrossOriginResourcePolicyStartupAction implements StartupAction
{
    public function __construct(
        private ?Closure $headerEmitter = null,
    )
    {
    }

    public function runAction(): void
    {
        $header = HttpHeader::CROSS_ORIGIN_RESOURCE_POLICY->value . ': same-site';
        if ($this->headerEmitter !== null) {
            ($this->headerEmitter)($header);
            return;
        }

        if (!headers_sent()) {
            header($header);
        }
    }
}
