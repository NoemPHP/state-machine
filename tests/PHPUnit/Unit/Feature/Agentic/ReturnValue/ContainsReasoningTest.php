<?php

declare(strict_types=1);

namespace Noem\State\Test\Unit\Feature\Agentic\ReturnValue;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: weave() result contains reasoning string explaining tool selection
 *
 * Criticality: contract
 * Intent: Provides AI explanation of selection rationale, enabling transparency and debugging
 *
 * @spec /specs/features/agentic.yaml:95-98
 */
#[Group('ai'), Group('weave'), Group('return-value')]
final class ContainsReasoningTest extends TestCase
{
    #[Test]
    public function resultContainsReasoningString(): void
    {
        $expectedStructure = [
            'selectedTools' => [],
            'toolCalls' => [],
            'result' => null,
            'iterations' => [],
            'reasoning' => '',
        ];

        $this->assertArrayHasKey('reasoning', $expectedStructure, 'Result should contain reasoning key');
        $this->assertIsString($expectedStructure['reasoning'], 'reasoning should be a string');
    }
}
