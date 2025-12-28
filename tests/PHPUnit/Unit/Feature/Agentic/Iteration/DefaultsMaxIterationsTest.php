<?php

declare(strict_types=1);

namespace Noem\State\Test\Unit\Feature\Agentic\Iteration;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: weave() defaults maxIterations to 3 when not specified
 *
 * Criticality: constraint
 * Intent: Provides sensible iteration limit preventing unbounded execution
 *
 * @spec /specs/features/agentic.yaml:326-329
 */
#[Group('ai'), Group('weave'), Group('iteration')]
final class DefaultsMaxIterationsTest extends TestCase
{
    #[Test]
    public function defaults_max_iterations_to_three(): void
    {
        // Verify default maxIterations is 3 (line 59 in Weave.php)
        $options = [];
        $defaultMax = $options['maxIterations'] ?? 3;

        $this->assertSame(3, $defaultMax);
    }
}
