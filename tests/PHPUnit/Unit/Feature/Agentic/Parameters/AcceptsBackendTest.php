<?php

declare(strict_types=1);

namespace Noem\State\Test\Unit\Feature\Agentic\Parameters;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: weave() options.backend accepts string for explicit backend override
 *
 * Criticality: contract
 * Intent: Enables explicit provider selection bypassing capability-based selection when needed
 *
 * @spec /specs/features/agentic.yaml:52-55
 */
#[Group('ai'), Group('weave'), Group('parameters')]
final class AcceptsBackendTest extends TestCase
{
    #[Test]
    public function weaveAcceptsBackendString(): void
    {
        $region = $this->createMock(\Noem\State\Region::class);
        $options = ['backend' => 'anthropic'];

        $params = new \Noem\State\Feature\Agentic\Chains\Params\Weave(
            region: $region,
            intent: 'test intent',
            options: $options
        );

        $this->assertArrayHasKey('backend', $params->options, 'WeaveParams should accept backend in options');
        $this->assertSame('anthropic', $params->options['backend']);
    }
}
