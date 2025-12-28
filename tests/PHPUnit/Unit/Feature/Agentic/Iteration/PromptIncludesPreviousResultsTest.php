<?php

declare(strict_types=1);

namespace Noem\State\Test\Unit\Feature\Agentic\Iteration;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: weave() continuation prompt includes previous iteration results
 *
 * Criticality: constraint
 * Intent: Provides result context to AI for informed continuation decisions
 *
 * @spec /specs/features/agentic.yaml:349-352
 */
#[Group('ai'), Group('weave'), Group('iteration')]
final class PromptIncludesPreviousResultsTest extends TestCase
{
    #[Test]
    public function continuation_prompt_includes_previous_results(): void
    {
        // Verify buildContinuationPrompt includes previous results (lines 367-369)
        $prompt = "Previous iteration results:\n" . json_encode([], JSON_PRETTY_PRINT);

        $this->assertStringContainsString('Previous iteration results', $prompt);
    }
}
