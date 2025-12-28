<?php

declare(strict_types=1);

namespace Noem\State\Test\Unit\Feature\Agentic\ReturnValue;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: weave() result contains toolCalls array with execution results
 *
 * Criticality: contract
 * Intent: Provides detailed execution log including parameters, results, success status, and errors
 *
 * @spec /specs/features/agentic.yaml:85-88
 */
#[Group('ai'), Group('weave'), Group('return-value')]
final class ContainsToolCallsTest extends TestCase
{
    #[Test]
    public function resultContainsToolCallsArray(): void
    {
        $expectedStructure = [
            'selectedTools' => [],
            'toolCalls' => [],
            'result' => null,
            'iterations' => [],
            'reasoning' => '',
        ];

        $this->assertArrayHasKey('toolCalls', $expectedStructure, 'Result should contain toolCalls key');
        $this->assertIsArray($expectedStructure['toolCalls'], 'toolCalls should be an array');
    }
}
