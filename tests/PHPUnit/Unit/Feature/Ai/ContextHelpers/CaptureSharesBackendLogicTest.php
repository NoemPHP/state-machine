<?php

declare(strict_types=1);

namespace Noem\State\Test\Unit\Feature\Ai\ContextHelpers;

use Noem\State\Feature\Ai\AiFeature;
use Noem\State\Middleware\ChainMail;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Context capture() method shares backend resolution logic
 * with template helper
 *
 * Intent: Maintains consistency between template and context helpers by using
 * shared backend selection implementation
 */
#[Group('ai'), Group('context-helpers')]
class CaptureSharesBackendLogicTest extends TestCase
{
    #[Test]
    public function captureSharesBackendResolutionLogic(): void
    {
        $chainMail = new ChainMail();
        $feature = new AiFeature();
        $feature($chainMail);

        // Test will verify both helpers use same backend resolution
        /**
         * ======================================================================
         * INTENTIONALLY SKIPPED - STUB TEST FOR FUTURE IMPLEMENTATION
         * ======================================================================
         *
         * This test is marked as skipped because the specification has been
         * approved but implementation is pending. This is intentional - the test
         * exists as a placeholder to ensure all acceptance criteria are tracked.
         *
         * Acceptance Criterion: Context capture() method shares backend resolution logic
         *
         * When implementing, refer to the specification for detailed requirements
         * and ensure all acceptance criteria are met before marking as complete.
         *
         * Related spec: specs/features/ai.yaml
         * ======================================================================
         */
        $this->markTestSkipped(
            'Implementation needed: shared backend logic'
        );
    }
}
