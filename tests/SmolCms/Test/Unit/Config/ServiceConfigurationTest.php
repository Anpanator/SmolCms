<?php

declare(strict_types=1);

namespace SmolCms\Test\Unit\Config;

use PHPUnit\Framework\TestCase;
use SmolCms\Config\ServiceConfiguration;
use SmolCms\Controller\BaseController;
use SmolCms\Data\Business\Service;
use SmolCms\Exception\ServiceConflictException;
use SmolCms\Service\Core\Router;

class ServiceConfigurationTest extends TestCase
{
    private ServiceConfiguration $serviceConfiguration;

    public function testAddService_successForNewService()
    {
        $serviceId = 'serviceId';
        $service = new Service(identifier: $serviceId, class: BaseController::class);
        $this->serviceConfiguration->addService($service);
        self::assertSame($serviceId, $this->serviceConfiguration->getServiceByIdentifier($serviceId)?->getIdentifier());
    }

    public function testAddService_successExistingServiceAndReplacementFlagSet()
    {
        $serviceId = 'serviceId123';
        $service = new Service(identifier: $serviceId, class: BaseController::class);
        $anotherServiceWithSameId = new Service(identifier: $serviceId, class: Router::class);
        $this->serviceConfiguration->addService($service);
        $this->serviceConfiguration->addService($anotherServiceWithSameId, true);
        self::assertSame($serviceId,
                         $this->serviceConfiguration->getServiceByIdentifier($serviceId)?->getIdentifier()
        );
        self::assertSame($anotherServiceWithSameId->getClass(),
                         $this->serviceConfiguration->getServiceByIdentifier($serviceId)?->getClass()
        );
    }

    public function testAddService_throwsExceptionWithDuplicateServiceIdAndNoReplacementFlag()
    {
        $serviceId = 'serviceId';
        $service = new Service(identifier: $serviceId, class: BaseController::class);
        $anotherServiceWithSameId = new Service(identifier: $serviceId, class: Router::class);
        $this->serviceConfiguration->addService($service);

        $this->expectException(ServiceConflictException::class);
        $this->serviceConfiguration->addService($anotherServiceWithSameId);
    }

    protected function setUp(): void
    {
        $this->serviceConfiguration = new ServiceConfiguration();
    }
}
