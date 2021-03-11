<?php

declare(strict_types=1);

namespace SmolCms\TestUtils;


use SmolCms\Config\ServiceConfiguration;
use SmolCms\Data\Business\ServiceRegistry;
use SmolCms\Service\Core\ApplicationCore;
use SmolCms\Service\Core\ServiceBuilder;

class FunctionalTestCase extends SimpleTestCase
{
    protected ApplicationCore $applicationCore;
    protected function setUp(): void
    {
        parent::setUp();
        $this->applicationCore = new ApplicationCore(
            new ServiceBuilder(
                new ServiceConfiguration(),
                new ServiceRegistry()
            )
        );
    }
}