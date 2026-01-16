<?php

declare(strict_types=1);

namespace Noem\State\Test\Unit\Feature\Ai\TemplateHelpers;

use Noem\State\Feature\Ai\AiFeature;
use Noem\State\Middleware\ChainMail;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Capture helper resolves schema from template data context when
 * schema parameter is string reference
 *
 * Intent: Enables schema reuse by supporting context variable references in schema parameter,
 * retrieving schema definition from template data at runtime
 */
#[Group('ai'), Group('template-helpers')]
class CaptureHelperResolvesSchemaFromContextTest extends TestCase
{
    #[Test]
    public function captureHelperResolvesSchemaFromContext(): void
    {
        $chainMail = new ChainMail();
        $feature = new AiFeature();
        $feature($chainMail);

        // Test will verify that when schema parameter is a string like "mySchema",
        // the helper looks it up in invocation data context

        // This requires actual invocation with data containing schema definition
        /**
         * ======================================================================
         * INTENTIONALLY SKIPPED - STUB TEST FOR FUTURE IMPLEMENTATION
         * ======================================================================
         *
         * This test is marked as skipped because the specification has been
         * approved but implementation is pending. This is intentional - the test
         * exists as a placeholder to ensure all acceptance criteria are tracked.
         *
         * Acceptance Criterion: Capture helper resolves schema from template data context when
         *
         * When implementing, refer to the specification for detailed requirements
         * and ensure all acceptance criteria are met before marking as complete.
         *
         * Related spec: specs/features/ai.yaml
         * ======================================================================
         */
        $this->markTestSkipped(
            'Implementation needed: schema resolution from context'
        );
    }
}
