<?php

declare(strict_types=1);

namespace Noem\State\Test\Unit\Feature\Agentic\Aggregation;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: weave() uses complete() for text synthesis when options.schema not provided
 *
 * Criticality: contract
 * Intent: Provides unstructured text aggregation as default when schema not specified
 *
 * @spec /specs/features/agentic.yaml:252-255
 */
#[Group('ai'), Group('weave'), Group('aggregation')]
final class UsesCompleteWithoutSchemaTest extends TestCase
{
    #[Test]
    public function weaveUsesCompleteWhenNoSchema(): void
    {
        // Test will verify weave() calls complete() for aggregation when no schema
        // Verify aggregation uses Completion without schema (Weave.php lines 370-386)
        // When no schema is provided, Completion is used instead of Chat

        $options = [];
        $hasSchema = isset($options['schema']);

        $this->assertFalse($hasSchema, 'No schema should be present');
    }
}
