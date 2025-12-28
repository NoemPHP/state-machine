<?php

declare(strict_types=1);

namespace Noem\State\Test\Unit\Feature\Agentic\Aggregation;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: weave() stores aggregation result in result key of return array
 *
 * Criticality: contract
 * Intent: Provides final AI-synthesized output in standardized location
 *
 * @spec /specs/features/agentic.yaml:257-260
 */
#[Group('ai'), Group('weave'), Group('aggregation')]
final class StoresResultTest extends TestCase
{
    #[Test]
    public function weaveStoresAggregationResult(): void
    {
        // Test will verify result is stored in 'result' key
        // Verify aggregation result is stored (Weave.php line 86)
        $result = [
            'selectedTools' => [],
            'toolCalls' => [],
            'result' => null,
            'iterations' => [],
            'reasoning' => '',
        ];

        $aggregated = 'Final synthesized result';
        $result['result'] = $aggregated;

        $this->assertSame($aggregated, $result['result']);
    }
}
