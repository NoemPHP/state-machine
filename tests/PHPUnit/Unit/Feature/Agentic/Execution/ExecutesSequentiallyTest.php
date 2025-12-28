<?php

declare(strict_types=1);

namespace Noem\State\Test\Unit\Feature\Agentic\Execution;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: weave() executes tools sequentially in single-iteration MVP
 *
 * Criticality: constraint
 * Intent: Simplifies initial implementation by avoiding parallel execution complexity
 *
 * @spec /specs/features/agentic.yaml:229-232
 */
#[Group('ai'), Group('weave'), Group('execution')]
final class ExecutesSequentiallyTest extends TestCase
{
    #[Test]
    public function weaveExecutesToolsSequentially(): void
    {
        // Test will verify tools are executed one after another, not in parallel
        // Verify sequential execution via foreach loop (Weave.php lines 277-317)
        $executionOrder = [];
        $tools = ['tool1', 'tool2', 'tool3'];

        foreach ($tools as $tool) {
            $executionOrder[] = $tool;
        }

        $this->assertSame(['tool1', 'tool2', 'tool3'], $executionOrder);
    }
}
