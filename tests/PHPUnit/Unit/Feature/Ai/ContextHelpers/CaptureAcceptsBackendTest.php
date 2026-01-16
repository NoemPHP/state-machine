<?php

declare(strict_types=1);

namespace Noem\State\Test\Unit\Feature\Ai\ContextHelpers;

use Noem\State\Feature\Ai\AiFeature;
use Noem\State\Middleware\ChainMail;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Context capture() method accepts optional backend parameter
 *
 * Intent: Enables backend selection through backend parameter, defaulting to
 * 'openai' when not specified
 */
#[Group('ai'), Group('context-helpers')]
class CaptureAcceptsBackendTest extends TestCase
{
    #[Test]
    public function captureAcceptsOptionalBackend(): void
    {
        $chainMail = new ChainMail();
        $feature = new AiFeature();
        $feature($chainMail);

        // Test will verify capture method signature accepts optional backend string
        /**
         * ======================================================================
         * INTENTIONALLY SKIPPED - STUB TEST FOR FUTURE IMPLEMENTATION
         * ======================================================================
         *
         * This test is marked as skipped because the specification has been
         * approved but implementation is pending. This is intentional - the test
         * exists as a placeholder to ensure all acceptance criteria are tracked.
         *
         * Acceptance Criterion: Context capture() method accepts optional backend parameter
         *
         * When implementing, refer to the specification for detailed requirements
         * and ensure all acceptance criteria are met before marking as complete.
         *
         * Related spec: specs/features/ai.yaml
         * ======================================================================
         */
        $this->markTestSkipped(
            'Implementation needed: backend parameter'
        );
    }
}
