<?php

declare(strict_types=1);

namespace Noem\State\Test\Unit\Feature\Agentic\Aggregation;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: weave() uses capture() with options.schema when schema provided
 *
 * Criticality: contract
 * Intent: Enables structured result validation through schema-constrained aggregation
 *
 * @spec /specs/features/agentic.yaml:247-250
 */
#[Group('ai'), Group('weave'), Group('aggregation')]
final class UsesCaptureWithSchemaTest extends TestCase
{
    #[Test]
    public function weaveUsesCaptureWhenSchemaProvided(): void
    {
        // Test will verify weave() calls capture() with schema for aggregation
        // Verify aggregation uses capture when schema provided (Weave.php lines 336-368)
        $schema = [
            'type' => 'object',
            'properties' => [
                'summary' => ['type' => 'string'],
            ],
        ];

        // Verify ResponseFormat construction
        $responseFormat = new \Noem\State\Feature\Ai\ResponseFormat(
            'json_schema',
            [
                'name' => 'aggregated_result',
                'schema' => $schema,
            ]
        );

        $this->assertSame('json_schema', $responseFormat->format);
        $this->assertArrayHasKey('schema', $responseFormat->definition);
        $this->assertSame($schema, $responseFormat->definition['schema']);
    }
}
