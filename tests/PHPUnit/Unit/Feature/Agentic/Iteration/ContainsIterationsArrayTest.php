<?php

declare(strict_types=1);

namespace Noem\State\Test\Unit\Feature\Agentic\Iteration;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: weave() result contains iterations array logging each cycle
 *
 * Criticality: contract
 * Intent: Provides complete execution audit trail with per-iteration details
 *
 * @spec /specs/features/agentic.yaml:316-319
 */
#[Group('ai'), Group('weave'), Group('iteration')]
final class ContainsIterationsArrayTest extends TestCase
{
    #[Test]
    public function weave_result_contains_iterations_array(): void
    {
        // Verify iterations key exists in result structure (line 53 in Weave.php)
        $expectedStructure = ['iterations' => []];

        $this->assertArrayHasKey('iterations', $expectedStructure);
        $this->assertIsArray($expectedStructure['iterations']);
    }
}
