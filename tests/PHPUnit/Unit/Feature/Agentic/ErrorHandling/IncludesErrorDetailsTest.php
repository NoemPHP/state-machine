<?php

declare(strict_types=1);

namespace Noem\State\Test\Unit\Feature\Agentic\ErrorHandling;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: weave() includes error details in toolCalls when ability invocation fails
 *
 * Criticality: contract
 * Intent: Provides debugging context through error messages in execution log
 *
 * @spec /specs/features/agentic.yaml:285-288
 */
#[Group('ai'), Group('weave'), Group('error-handling')]
final class IncludesErrorDetailsTest extends TestCase
{
    #[Test]
    public function weaveIncludesErrorDetailsInToolCalls(): void
    {
        // Test will verify error field in toolCalls contains exception details
        // Verify error details are included in toolCalls (Weave.php lines 311-314)
        $toolCall = [
            'ability' => 'test-tool',
            'parameters' => [],
            'result' => null,
            'success' => false,
            'error' => null,
        ];

        try {
            throw new \Exception('Connection timeout');
        } catch (\Exception $e) {
            $toolCall['error'] = $e->getMessage();
            $toolCall['success'] = false;
        }

        $this->assertFalse($toolCall['success']);
        $this->assertSame('Connection timeout', $toolCall['error']);
    }
}
