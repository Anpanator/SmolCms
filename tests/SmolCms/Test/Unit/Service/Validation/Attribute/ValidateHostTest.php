<?php

declare(strict_types=1);

namespace SmolCms\Test\Unit\Service\Validation\Attribute;

use SmolCms\Service\Validation\Attribute\ValidateHost;
use SmolCms\TestUtils\SimpleTestCase;

class ValidateHostTest extends SimpleTestCase
{
    public function testValidate_successIPV4AllowAll()
    {
        $validateHost = new ValidateHost(ValidateHost::ALLOW_ALL);
        self::assertTrue($validateHost->validate('192.168.0.1'));
    }

    public function testValidate_successIPV6AllowAll()
    {
        $validateHost = new ValidateHost(ValidateHost::ALLOW_ALL);
        self::assertTrue($validateHost->validate('::1'));
    }

    public function testValidate_successDomainAllowAll()
    {
        $validateHost = new ValidateHost(ValidateHost::ALLOW_ALL);
        self::assertTrue($validateHost->validate('example.com'));
    }

    public function testValidate_successIPv4AllowIPv6andIPv4()
    {
        $validateHost = new ValidateHost(ValidateHost::IPV4 | ValidateHost::IPV6);
        self::assertTrue($validateHost->validate('8.8.8.8'));
    }

    public function testValidate_failureIPv4AllowIPv6()
    {
        $validateHost = new ValidateHost(ValidateHost::IPV6);
        self::assertFalse($validateHost->validate('192.168.0.1'));
    }

    public function testValidate_failureIPv6AllowIPv4()
    {
        $validateHost = new ValidateHost(ValidateHost::IPV4);
        self::assertFalse($validateHost->validate('::1'));
    }

    public function testValidate_failureDomainAllowIPv6()
    {
        $validateHost = new ValidateHost(ValidateHost::IPV6);
        self::assertFalse($validateHost->validate('example.com'));
    }

}
