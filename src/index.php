<?php
declare(strict_types=1);

use SmolCms\Config\RoutingConfiguration;

require_once __DIR__ . '/' . '../vendor/autoload.php';



$routeConfig = new RoutingConfiguration();
var_dump($routeConfig->getRoutes());