<?php

declare(strict_types=1);

namespace Noem\State\Test\Unit\Feature\Agentic\Parameters;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: weave() options.tools accepts array of ability name patterns for scope filtering
 *
 * Criticality: contract
 * Intent: Restricts tool scope through pattern matching, enabling security and performance optimization
 *
 * @spec /specs/features/agentic.yaml:42-45
 */
#[Group('ai'), Group('weave'), Group('parameters')]
final class AcceptsToolsFilterTest extends TestCase
{
    #[Test]
    public function weaveAcceptsToolsFilterArray(): void
    {
        $region = $this->createMock(\Noem\State\Region::class);
        $options = ['tools' => ['user-*', 'analytics-report']];

        $params = new \Noem\State\Feature\Agentic\Chains\Params\Weave(
            region: $region,
            intent: 'test intent',
            options: $options
        );

        $this->assertArrayHasKey('tools', $params->options, 'WeaveParams should accept tools filter in options');
        $this->assertSame(['user-*', 'analytics-report'], $params->options['tools']);
    }
}
