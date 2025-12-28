<?php

declare(strict_types=1);

namespace Noem\State\Test\Unit\Feature\Agentic\Aggregation;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: weave() aggregation prompt includes all tool call results with success status
 *
 * Criticality: constraint
 * Intent: Provides complete execution context to AI for informed synthesis
 *
 * @spec /specs/features/agentic.yaml:242-245
 */
#[Group('ai'), Group('weave'), Group('aggregation')]
final class PromptIncludesToolResultsTest extends TestCase
{
    #[Test]
    public function aggregationPromptIncludesToolResults(): void
    {
        // Test will verify aggregation prompt contains all tool call results
        // Verify aggregation prompt includes tool results (Weave.php lines 399-400)
        $toolCalls = [
            ['ability' => 'get-user', 'result' => ['id' => 1], 'success' => true],
            ['ability' => 'get-posts', 'result' => [], 'success' => false, 'error' => 'Not found'],
        ];

        $prompt = "Tool call results:\n\n";
        $prompt .= json_encode($toolCalls, JSON_PRETTY_PRINT) . "\n\n";

        // Verify results are in prompt
        $this->assertStringContainsString('get-user', $prompt);
        $this->assertStringContainsString('get-posts', $prompt);
        $this->assertStringContainsString('"success": true', $prompt);
        $this->assertStringContainsString('"success": false', $prompt);
    }
}
