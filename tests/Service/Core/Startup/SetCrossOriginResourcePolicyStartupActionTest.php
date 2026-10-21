<?php
declare(strict_types=1);

namespace SmolCms\Test\Service\Core\Startup;

use SmolCms\Service\Core\Startup\SetCrossOriginResourcePolicyStartupAction;
use SmolCms\TestUtils\SimpleTestCase;

class SetCrossOriginResourcePolicyStartupActionTest extends SimpleTestCase
{
    public function testRunAction_EmitsCompleteHeaderLineWithoutOutput(): void
    {
        $emittedHeaders = [];
        $action = new SetCrossOriginResourcePolicyStartupAction(
            static function (string $header) use (&$emittedHeaders): void {
                $emittedHeaders[] = $header;
            },
        );

        ob_start();
        $action->runAction();
        $output = ob_get_clean();

        self::assertSame(['Cross-Origin-Resource-Policy: same-site'], $emittedHeaders);
        self::assertSame('', $output);
    }
}
