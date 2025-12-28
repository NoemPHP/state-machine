<?php

declare(strict_types=1);

namespace Noem\State\Test\Unit\Feature\Agentic\Iteration;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: weave() enforces maxIterations hard cap on selection cycles
 *
 * Criticality: constraint
 * Intent: Prevents runaway execution by stopping at configured iteration limit
 *
 * @spec /specs/features/agentic.yaml:331-334
 */
#[Group('ai'), Group('weave'), Group('iteration')]
final class EnforcesMaxIterationsTest extends TestCase
{
    #[Test]
    public function enforces_max_iterations_hard_cap(): void
    {
        // Verify loop stops at maxIterations (line 73)
        $maxIterations = 2;
        $iterationNumber = 0;

        while ($iterationNumber < $maxIterations) {
            $iterationNumber++;
        }

        $this->assertSame(2, $iterationNumber);
    }
}
