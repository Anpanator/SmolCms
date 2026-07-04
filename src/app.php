<?php

declare(strict_types=1);

use SmolCms\Config\CoreServiceConfiguration;
use SmolCms\Data\Business\ServiceRegistry;
use SmolCms\Service\Core\ApplicationCore;
use SmolCms\Service\Core\ServiceBuilder;

require_once dirname(__DIR__) . '/constants.php';
require_once ROOT_DIR . '/vendor/autoload.php';

$serviceBuilder = new ServiceBuilder(
    new CoreServiceConfiguration(),
    new ServiceRegistry()
);
/** @var ApplicationCore $applicationCore */
$applicationCore = $serviceBuilder->getService(ApplicationCore::class);
$applicationCore->init();
$applicationCore->runCli();
$applicationCore->runCgi();
/*$response = $applicationCore->simulateRequest(
    new Request(
        url: new Url(protocol: 'https', host: 'localhost', path: '/login'),
        method: HttpMethod::POST,
        postParams: ['loginName' => 'admin', 'password' => 'admin123'],
    )
);
var_dump($response);*/
