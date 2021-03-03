<?php

declare(strict_types=1);

namespace SmolCms\Test\Integration\Service\Validation;

use SmolCms\Service\Validation\Attribute\ValidateAllowList;
use SmolCms\Service\Validation\Attribute\ValidateDenyList;
use SmolCms\Service\Validation\Attribute\ValidateRange;
use SmolCms\Service\Validation\Validator;
use SmolCms\TestUtils\SimpleTestCase;

class ValidatorTest extends SimpleTestCase
{
    private Validator $validator;

    public function testValidate_success()
    {
        $testHelper = new TestHelper(float: 10.0, int: 0, mixedAllow: 'ALLOWED', stringDeny: 'NOT_DENIED');
        $validationResult = $this->validator->validate($testHelper);
        self::assertSame(true, $validationResult->isValid());
        self::assertEmpty($validationResult->getMessages());
    }

    public function testValidate_failureWithSingleValueOutOfRange()
    {
        $testHelper = new TestHelper(float: -9999.1, int: 0, mixedAllow: 'ALLOWED', stringDeny: 'NOT_DENIED');
        $validationResult = $this->validator->validate($testHelper);
        self::assertSame(false, $validationResult->isValid());
        $messages = $validationResult->getMessages();
        self::assertCount(1, $messages);
        self::assertArrayHasKey('float', $messages);
    }

    public function testValidate_successWithNestedObjectValidation()
    {
        self::fail('Implement ' . __METHOD__);
    }

    public function testValidate_failureWithNestedObjectValidation()
    {
        self::fail('Implement ' . __METHOD__);
    }

    public function testValidate_failureWithMultipleValuesOutOfRange()
    {
        $floatValue = -9999.2;
        $intValue = 9999;
        $mixedAllowValue = 'NOT_ALLOWED';
        $stringDenyValue = 'DENIED';
        $testHelper = new TestHelper(
            float: $floatValue,
            int: $intValue,
            mixedAllow: $mixedAllowValue,
            stringDeny: $stringDenyValue
        );
        $validationResult = $this->validator->validate($testHelper);
        self::assertSame(false, $validationResult->isValid());
        $messages = $validationResult->getMessages();
        self::assertCount(4, $messages);

        self::assertArrayHasKey('float', $messages);
        self::assertStringContainsString((string)$floatValue, $messages['float']);

        self::assertArrayHasKey('int', $messages);
        self::assertStringContainsString((string)$intValue, $messages['int']);

        self::assertArrayHasKey('mixedAllow', $messages);
        self::assertStringContainsString($mixedAllowValue, $messages['mixedAllow']);

        self::assertArrayHasKey('stringDeny', $messages);
        self::assertStringContainsString($stringDenyValue, $messages['stringDeny']);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = new Validator();
    }
}

class TestHelper
{

    /**
     * TestHelper constructor.
     * @param float $float
     * @param int $int
     * @param mixed $mixedAllow
     * @param mixed $stringDeny
     */
    public function __construct(
        #[ValidateRange(min: 10, max: 100)]
        private float $float,
        #[ValidateRange(min: -10, max: 0)]
        private int $int,
        #[ValidateAllowList(['ALLOWED', 1, true])]
        private mixed $mixedAllow,
        #[ValidateDenyList(['DENIED', 'ALSO_DENIED'])]
        private string $stringDeny,
    ) {
    }
}