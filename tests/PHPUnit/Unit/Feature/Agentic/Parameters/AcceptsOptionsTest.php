<?php

declare(strict_types=1);

namespace Noem\State\Test\Unit\Feature\Agentic\Parameters;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: weave() accepts optional options array as second parameter
 *
 * Criticality: contract
 * Intent: Enables configuration customization through optional parameter, supporting
 * tools filtering, iteration limits, and backend selection
 *
 * @spec /specs/features/agentic.yaml:37-40
 */
#[Group('ai'), Group('weave'), Group('parameters')]
final class AcceptsOptionsTest extends TestCase
{
    #[Test]
    public function weaveAcceptsOptionsArray(): void
    {
        $region = $this->createMock(\Noem\State\Region::class);
        $options = ['backend' => 'ollama', 'maxIterations' => 3];

        $params = new \Noem\State\Feature\Agentic\Chains\Params\Weave(
            region: $region,
            intent: 'test intent',
            options: $options
        );

        $this->assertSame($options, $params->options, 'WeaveParams should accept and store options array');
    }
}
