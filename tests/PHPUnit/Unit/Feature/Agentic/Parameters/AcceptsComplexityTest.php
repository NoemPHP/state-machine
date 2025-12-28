<?php

declare(strict_types=1);

namespace Noem\State\Test\Unit\Feature\Agentic\Parameters;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: weave() options.complexity accepts string for capability-based backend selection
 *
 * Criticality: contract
 * Intent: Enables complexity-based model selection through capability system integration
 *
 * @spec /specs/features/agentic.yaml:57-60
 */
#[Group('ai'), Group('weave'), Group('parameters')]
final class AcceptsComplexityTest extends TestCase
{
    #[Test]
    public function weaveAcceptsComplexityString(): void
    {
        $region = $this->createMock(\Noem\State\Region::class);
        $options = ['complexity' => 'high'];

        $params = new \Noem\State\Feature\Agentic\Chains\Params\Weave(
            region: $region,
            intent: 'test intent',
            options: $options
        );

        $this->assertArrayHasKey('complexity', $params->options, 'WeaveParams should accept complexity in options');
        $this->assertSame('high', $params->options['complexity']);
    }
}
