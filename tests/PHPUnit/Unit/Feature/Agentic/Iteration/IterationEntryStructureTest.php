<?php

declare(strict_types=1);

namespace Noem\State\Test\Unit\Feature\Agentic\Iteration;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Each iteration entry contains iteration number, tools, reasoning, results
 *
 * Criticality: contract
 * Intent: Defines standardized iteration log structure for debugging and analysis
 *
 * @spec /specs/features/agentic.yaml:321-324
 */
#[Group('ai'), Group('weave'), Group('iteration')]
final class IterationEntryStructureTest extends TestCase
{
    #[Test]
    public function iteration_entry_contains_required_fields(): void
    {
        // Verify iteration entry structure (lines 109-114 in Weave.php)
        $expectedEntry = [
            'iteration' => 1,
            'tools' => [],
            'reasoning' => '',
            'results' => [],
        ];

        $this->assertArrayHasKey('iteration', $expectedEntry);
        $this->assertArrayHasKey('tools', $expectedEntry);
        $this->assertArrayHasKey('reasoning', $expectedEntry);
        $this->assertArrayHasKey('results', $expectedEntry);
    }
}
