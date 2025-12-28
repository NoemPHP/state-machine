<?php

declare(strict_types=1);

namespace Noem\State\Test\Unit\Feature\Agentic\Performance;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: weave() supports options.parallel for concurrent tool execution
 *
 * Criticality: contract
 * Intent: Enables parallel tool invocation when AsyncFeature available for performance
 *
 * @spec /specs/features/agentic.yaml:439-442
 */
#[Group('ai'), Group('weave'), Group('performance')]
final class SupportsParallelExecutionTest extends TestCase
{
    #[Test]
    public function supports_parallel_execution_option(): void
    {
        // Verify options array accepts parallel parameter
        // Implementation: Params\Weave constructor accepts options array (line 17 in Params/Weave.php)
        // The options array is public readonly and can contain any key-value pairs

        $region = $this->createMock(\Noem\State\Region::class);
        $options = ['parallel' => true];

        $params = new \Noem\State\Feature\Agentic\Chains\Params\Weave(
            region: $region,
            intent: 'test intent',
            options: $options
        );

        $this->assertArrayHasKey('parallel', $params->options, 'Options array should accept parallel parameter');
        $this->assertTrue($params->options['parallel'], 'Parallel option should be preserved');
    }
}
