<?php

declare(strict_types=1);

use SmolCms\Config\CoreServiceConfiguration;
use SmolCms\Data\Business\ServiceRegistry;
use SmolCms\Service\Core\ApplicationCore;
use SmolCms\Service\Core\ServiceBuilder;

require_once dirname(__DIR__) . '/constants.php';
require_once ROOT_DIR . '/vendor/autoload.php';

$applicationCore = new ApplicationCore(
    new ServiceBuilder(
        new CoreServiceConfiguration(),
        new ServiceRegistry()
    )
);
$applicationCore->run();
/*$response = $applicationCore->simulateRequest(
    new Request(
        url: new Url(protocol: 'https', host: 'localhost', path: '/login'),
        method: HttpMethod::POST,
        postParams: ['loginName' => "Anpana", 'password' => 'bestPassword!'],
    )
);
echo <<<HTML
<pre>
    print_r($response, true);
</pre>
HTML;*/
