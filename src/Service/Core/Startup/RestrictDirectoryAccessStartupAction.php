<?php
declare(strict_types=1);

namespace SmolCms\Service\Core\Startup;

readonly class RestrictDirectoryAccessStartupAction implements StartupAction
{
    private array $directoryWhitelist;

    public function __construct(
        string ...$directoryWhitelist
    )
    {
        $this->directoryWhitelist = $directoryWhitelist;
    }

    public function runAction(): void
    {
        $builtWhitelist = implode(':', $this->directoryWhitelist);
        ini_set('open_basedir', $builtWhitelist);
    }
}
