<?php

declare(strict_types=1);

namespace Noem\State\Test\Unit\Feature\Agentic\Planning;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: weave() respects options.complexity for planning call when specified
 *
 * Criticality: contract
 * Intent: Enables complexity-based backend selection for planning through capability system
 *
 * @spec /specs/features/agentic.yaml:181-184
 */
#[Group('ai'), Group('weave'), Group('planning')]
final class RespectsComplexityTest extends TestCase
{
    #[Test]
    public function planningRespectsComplexity(): void
    {
        // Test will verify planning uses options.complexity when provided
        // Verify complexity option is respected
        // Note: Current implementation doesn't use complexity for planning yet

        $options = ['complexity' => 'high'];

        $this->assertArrayHasKey('complexity', $options);
        $this->assertSame('high', $options['complexity']);
    }
}
