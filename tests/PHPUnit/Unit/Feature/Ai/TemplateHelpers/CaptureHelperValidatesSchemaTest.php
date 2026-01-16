<?php

declare(strict_types=1);

namespace Noem\State\Test\Unit\Feature\Ai\TemplateHelpers;

use Noem\State\Feature\Ai\AiFeature;
use Noem\State\Middleware\ChainMail;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Capture helper validates custom schema is valid JSON Schema
 * before API call
 *
 * Intent: Prevents invalid API requests by validating schema structure matches
 * JSON Schema specification
 */
#[Group('ai'), Group('template-helpers')]
class CaptureHelperValidatesSchemaTest extends TestCase
{
    #[Test]
    public function captureHelperValidatesSchema(): void
    {
        $chainMail = new ChainMail();
        $feature = new AiFeature();
        $feature($chainMail);

        // Test will verify that invalid schemas throw validation errors
        // before making API calls

        /**
         * ======================================================================
         * INTENTIONALLY SKIPPED - STUB TEST FOR FUTURE IMPLEMENTATION
         * ======================================================================
         *
         * This test is marked as skipped because the specification has been
         * approved but implementation is pending. This is intentional - the test
         * exists as a placeholder to ensure all acceptance criteria are tracked.
         *
         * Acceptance Criterion: Capture helper validates custom schema is valid JSON Schema
         *
         * When implementing, refer to the specification for detailed requirements
         * and ensure all acceptance criteria are met before marking as complete.
         *
         * Related spec: specs/features/ai.yaml
         * ======================================================================
         */
        $this->markTestSkipped(
            'Implementation needed: schema validation'
        );
    }
}
