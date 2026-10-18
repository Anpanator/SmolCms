<?php

declare(strict_types=1);

/**
 * Composer script (composer check-chromium, post-install/update hook) that verifies
 * a Chromium binary is available for the frontend test suite. Warns but does not
 * fail: chromium is only needed to run vendor/bin/phpunit, not to install the app.
 */

const MISSING_CHROMIUM_WARNING = <<<'MSG'
    [warning] No Chromium binary found. The frontend test suite requires headless Chromium
              and vendor/bin/phpunit cannot run completely without it. Either install chromium
              so it can be located via "command -v chromium", or set the CHROMIUM_BINARY
              environment variable to the binary path.
    MSG;

function locateChromiumBinary(): ?string
{
    $envBinary = getenv('CHROMIUM_BINARY');
    if (is_string($envBinary) && $envBinary !== '' && is_executable($envBinary)) {
        return $envBinary;
    }

    $located = trim((string)shell_exec('command -v chromium 2>/dev/null'));

    return $located !== '' && is_executable($located) ? $located : null;
}

$binary = locateChromiumBinary();
if ($binary === null) {
    fwrite(STDERR, MISSING_CHROMIUM_WARNING . PHP_EOL);

    exit(0);
}

printf('Chromium binary: %s%s', $binary, PHP_EOL);
