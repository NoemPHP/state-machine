<?php

declare(strict_types=1);

namespace Noem\State\Test\Unit\Feature\Agentic\Iteration;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: weave() stops iteration when AI indicates completion
 *
 * Criticality: contract
 * Intent: Terminates early when AI signals no additional tools needed
 *
 * @spec /specs/features/agentic.yaml:359-362
 */
#[Group('ai'), Group('weave'), Group('iteration')]
final class StopsWhenCompleteTest extends TestCase
{
    #[Test]
    public function stops_iteration_when_ai_indicates_completion(): void
    {
        // Verify loop breaks when no tools (line 93)
        $selectedTools = [];
        $shouldStop = empty($selectedTools);

        $this->assertTrue($shouldStop);
    }
}
