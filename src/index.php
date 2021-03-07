<?php

declare(strict_types=1);

use SmolCms\Config\ServiceConfiguration;
use SmolCms\Data\Business\ServiceRegistry;
use SmolCms\Service\Core\ApplicationCore;
use SmolCms\Service\Core\ServiceBuilder;

require_once __DIR__ . '/' . '../vendor/autoload.php';

$applicationCore = new ApplicationCore(
    new ServiceBuilder(
        new ServiceConfiguration(),
        new ServiceRegistry()
    )
);

$applicationCore->run();
