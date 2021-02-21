<?php

declare(strict_types=1);

namespace SmolCms\Test\Unit\Data\Business\Routing;

use Exception;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use SmolCms\Data\Business\Service;

class ServiceTest extends TestCase
{
    public function testConstruct_successWithIdentifierAndClassSet() {
        $service = new Service(
            identifier: 'something', class: Exception::class
        );
        self::assertNotNull($service);
    }

    public function testConstruct_classDoesNotExistThrowsInvalidArgumentException() {
        $this->expectException(InvalidArgumentException::class);
        new Service(
            identifier: 'something', class: 'I DONT EXIST'
        );
    }

    public function testConstruct_successWithClassSetFromIdentifier() {
        $service = new Service(
            identifier: Exception::class
        );
        self::assertNotNull($service);
    }

    public function testConstruct_nonexistingClassFromIdentifierThrowsInvalidArgumentException() {
        $this->expectException(InvalidArgumentException::class);
        new Service(
            identifier: 'ID, BUT NOT A CLASS'
        );
    }
}
