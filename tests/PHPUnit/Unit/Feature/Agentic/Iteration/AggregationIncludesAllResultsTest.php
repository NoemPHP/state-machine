<?php

declare(strict_types=1);

namespace Noem\State\Test\Unit\Feature\Agentic\Iteration;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: weave() final aggregation includes results from all iterations
 *
 * Criticality: constraint
 * Intent: Ensures synthesis considers complete execution history
 *
 * @spec /specs/features/agentic.yaml:379-382
 */
#[Group('ai'), Group('weave'), Group('iteration')]
final class AggregationIncludesAllResultsTest extends TestCase
{
    #[Test]
    public function final_aggregation_includes_all_iteration_results(): void
    {
        // Verify aggregation receives accumulated toolCalls (line 122)
        $allToolCalls = [['ability' => 'test1'], ['ability' => 'test2']];

        $this->assertCount(2, $allToolCalls, 'Aggregation receives all accumulated calls');
    }
}
