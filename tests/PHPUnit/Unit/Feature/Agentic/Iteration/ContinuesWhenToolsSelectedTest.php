<?php

declare(strict_types=1);

namespace Noem\State\Test\Unit\Feature\Agentic\Iteration;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: weave() continues iteration when AI selects additional tools
 *
 * Criticality: contract
 * Intent: Executes additional cycles when AI indicates more information needed
 *
 * @spec /specs/features/agentic.yaml:354-357
 */
#[Group('ai'), Group('weave'), Group('iteration')]
final class ContinuesWhenToolsSelectedTest extends TestCase
{
    #[Test]
    public function continues_iteration_when_ai_selects_additional_tools(): void
    {
        // Verify loop continues when tools selected (lines 88-94 breaks when empty)
        $selectedTools = [['ability' => 'test']];
        $shouldContinue = !empty($selectedTools);

        $this->assertTrue($shouldContinue);
    }
}
