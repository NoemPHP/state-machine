<?php

declare(strict_types=1);

namespace Noem\State\Test\Unit\Feature\Ai;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: AiFeature registers template helpers in Helpers service
 */
#[Group('ai'), Group('feature-registration')]
class RegistersTemplateHelpersTest extends TestCase
{
    #[Test]
    public function registersTemplateHelpers(): void
    {
        $chainMail = new \Noem\State\Middleware\ChainMail();

        // Helpers service needs to be registered first
        $helpers = new \Noem\State\Feature\Template\Helpers();
        $chainMail->supply(fn(): \Noem\State\Feature\Template\Helpers => $helpers);

        $feature = new \Noem\State\Feature\Ai\AiFeature();
        $feature($chainMail);

        // Boot the ChainMail to execute the middleware that registers helpers
        $chainMail->boot();

        // Verify that both 'complete' and 'capture' helpers are registered
        $this->assertTrue(isset($helpers['complete']), 'complete helper should be registered');
        $this->assertTrue(isset($helpers['capture']), 'capture helper should be registered');
    }
}
