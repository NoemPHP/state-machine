<?php

declare(strict_types=1);

namespace Noem\State\Test\Unit\Feature\Ai\ContextHelpers;

use Noem\State\Feature\Ai\AiFeature;
use Noem\State\Middleware\ChainMail;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Context capture() method shares schema resolution logic
 * with template helper
 *
 * Intent: Maintains consistency between template and context helpers by using
 * shared schema processing implementation
 */
#[Group('ai'), Group('context-helpers')]
class CaptureSharesSchemaLogicTest extends TestCase
{
    #[Test]
    public function captureSharesSchemaResolutionLogic(): void
    {
        $chainMail = new ChainMail();
        $feature = new AiFeature();
        $feature($chainMail);

        // Test will verify both helpers use same schema resolution
        $this->markTestIncomplete('Implementation needed: shared schema logic');
    }
}
