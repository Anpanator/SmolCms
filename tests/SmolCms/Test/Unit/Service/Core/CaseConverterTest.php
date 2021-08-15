<?php
declare(strict_types=1);

namespace SmolCms\Test\Unit\Service\Core;

use SmolCms\Service\Core\CaseConverter;
use SmolCms\TestUtils\SimpleTestCase;

class CaseConverterTest extends SimpleTestCase
{
    private CaseConverter $caseConverter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->caseConverter = new CaseConverter();
    }

    public function testSnakeCaseToCamelCase_success(): void
    {
        $testString = 'I_lIkE_TrainS';
        $expectedString = 'iLikeTrains';
        $result = $this->caseConverter->snakeCaseToCamelCase($testString);
        self::assertSame($expectedString, $result);
    }

    public function testCamelCaseToSnakeCase_success(): void
    {
        $testString = 'thisIsCamelCaseWithPOWAAAA';
        $expectedString = 'this_is_camel_case_with_p_o_w_a_a_a_a';
        $result = $this->caseConverter->camelCaseToSnakeCase($testString);
        self::assertSame($expectedString, $result);
    }
}