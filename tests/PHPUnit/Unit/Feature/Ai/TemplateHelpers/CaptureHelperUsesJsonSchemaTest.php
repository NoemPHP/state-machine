<?php

declare(strict_types=1);

namespace Noem\State\Test\Unit\Feature\Ai\TemplateHelpers;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Capture helper uses JSON schema response format from schema
 * parameter or defaults to array of strings
 *
 * Intent: Enables custom structured extraction by accepting optional schema parameter,
 * falling back to default array<string> schema for backward compatibility
 */
#[Group('ai'), Group('template-helpers')]
class CaptureHelperUsesJsonSchemaTest extends TestCase
{
    #[Test]
    public function captureHelperUsesDefaultArrayOfStringsSchema(): void
    {
        // When no schema parameter provided, should use default array<string> schema
        /**
         * ======================================================================
         * INTENTIONALLY SKIPPED - STUB TEST FOR FUTURE IMPLEMENTATION
         * ======================================================================
         *
         * This test is marked as skipped because the specification has been
         * approved but implementation is pending. This is intentional - the test
         * exists as a placeholder to ensure all acceptance criteria are tracked.
         *
         * Acceptance Criterion: Capture helper uses JSON schema response format from schema
         *
         * When implementing, refer to the specification for detailed requirements
         * and ensure all acceptance criteria are met before marking as complete.
         *
         * Related spec: specs/features/ai.yaml
         * ======================================================================
         */
        $this->markTestSkipped(
            'Implementation needed: default schema'
        );
    }

    #[Test]
    public function captureHelperUsesCustomSchema(): void
    {
        // When schema parameter provided, should use that schema instead
        /**
         * ======================================================================
         * INTENTIONALLY SKIPPED - STUB TEST FOR FUTURE IMPLEMENTATION
         * ======================================================================
         *
         * This test is marked as skipped because the specification has been
         * approved but implementation is pending. This is intentional - the test
         * exists as a placeholder to ensure all acceptance criteria are tracked.
         *
         * Acceptance Criterion: Capture helper uses JSON schema response format from schema
         *
         * When implementing, refer to the specification for detailed requirements
         * and ensure all acceptance criteria are met before marking as complete.
         *
         * Related spec: specs/features/ai.yaml
         * ======================================================================
         */
        $this->markTestSkipped(
            'Implementation needed: default schema'
        );
    }
}
