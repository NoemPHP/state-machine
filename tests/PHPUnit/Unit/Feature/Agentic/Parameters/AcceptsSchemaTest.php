<?php

declare(strict_types=1);

namespace Noem\State\Test\Unit\Feature\Agentic\Parameters;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: weave() options.schema accepts array for final result validation
 *
 * Criticality: contract
 * Intent: Enables structured output validation through JSON Schema application to final result
 *
 * @spec /specs/features/agentic.yaml:67-70
 */
#[Group('ai'), Group('weave'), Group('parameters')]
final class AcceptsSchemaTest extends TestCase
{
    #[Test]
    public function weaveAcceptsSchemaArray(): void
    {
        $region = $this->createMock(\Noem\State\Region::class);
        $schema = ['type' => 'object', 'properties' => ['result' => ['type' => 'string']]];
        $options = ['schema' => $schema];

        $params = new \Noem\State\Feature\Agentic\Chains\Params\Weave(
            region: $region,
            intent: 'test intent',
            options: $options
        );

        $this->assertArrayHasKey('schema', $params->options, 'WeaveParams should accept schema in options');
        $this->assertSame($schema, $params->options['schema']);
    }
}
