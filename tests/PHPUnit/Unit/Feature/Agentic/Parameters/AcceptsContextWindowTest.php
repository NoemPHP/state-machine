<?php

declare(strict_types=1);

namespace Noem\State\Test\Unit\Feature\Agentic\Parameters;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: weave() options.context accepts string for context window requirement
 *
 * Criticality: contract
 * Intent: Enables context-aware model selection through capability system integration
 *
 * @spec /specs/features/agentic.yaml:62-65
 */
#[Group('ai'), Group('weave'), Group('parameters')]
final class AcceptsContextWindowTest extends TestCase
{
    #[Test]
    public function weaveAcceptsContextWindowString(): void
    {
        $region = $this->createMock(\Noem\State\Region::class);
        $options = ['context' => 'large'];

        $params = new \Noem\State\Feature\Agentic\Chains\Params\Weave(
            region: $region,
            intent: 'test intent',
            options: $options
        );

        $this->assertArrayHasKey('context', $params->options, 'WeaveParams should accept context window in options');
        $this->assertSame('large', $params->options['context']);
    }
}
