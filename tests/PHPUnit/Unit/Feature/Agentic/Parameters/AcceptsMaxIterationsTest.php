<?php

declare(strict_types=1);

namespace Noem\State\Test\Unit\Feature\Agentic\Parameters;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: weave() options.maxIterations accepts integer limiting selection cycles
 *
 * Criticality: contract
 * Intent: Prevents runaway execution through hard iteration cap, defaulting to 3 iterations
 *
 * @spec /specs/features/agentic.yaml:47-50
 */
#[Group('ai'), Group('weave'), Group('parameters')]
final class AcceptsMaxIterationsTest extends TestCase
{
    #[Test]
    public function weaveAcceptsMaxIterationsInteger(): void
    {
        $region = $this->createMock(\Noem\State\Region::class);
        $options = ['maxIterations' => 5];

        $params = new \Noem\State\Feature\Agentic\Chains\Params\Weave(
            region: $region,
            intent: 'test intent',
            options: $options
        );

        $this->assertArrayHasKey('maxIterations', $params->options, 'WeaveParams should accept maxIterations in options');
        $this->assertSame(5, $params->options['maxIterations']);
    }
}
