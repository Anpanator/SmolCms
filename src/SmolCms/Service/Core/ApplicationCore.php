<?php

declare(strict_types=1);

namespace SmolCms\Service\Core;


class ApplicationCore
{

    /**
     * ApplicationCore constructor.
     * @param ServiceBuilder $serviceBuilder
     */
    public function __construct(private ServiceBuilder $serviceBuilder)
    {
    }

    public function run(): void
    {
        echo "test";
    }
}