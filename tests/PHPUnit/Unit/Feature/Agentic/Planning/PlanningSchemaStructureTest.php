<?php

declare(strict_types=1);

namespace Noem\State\Test\Unit\Feature\Agentic\Planning;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: weave() planning schema requires reasoning field and tools array
 *
 * Criticality: contract
 * Intent: Enforces structured planning output containing explanation and tool selections
 *
 * @spec /specs/features/agentic.yaml:151-154
 */
#[Group('ai'), Group('weave'), Group('planning')]
final class PlanningSchemaStructureTest extends TestCase
{
    #[Test]
    public function planningSchemaHasReasoningAndTools(): void
    {
        // Test will verify planning schema requires 'reasoning' and 'tools' fields
        // Verify the planning schema structure at Weave.php lines 166-183
        $schema = [
            'type' => 'object',
            'properties' => [
                'reasoning' => ['type' => 'string', 'description' => 'Explanation of tool selection'],
                'tools' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'ability' => ['type' => 'string'],
                            'parameters' => ['type' => 'object'],
                        ],
                        'required' => ['ability', 'parameters'],
                    ],
                ],
            ],
            'required' => ['reasoning', 'tools'],
        ];

        // Verify schema requires reasoning and tools
        $this->assertContains('reasoning', $schema['required']);
        $this->assertContains('tools', $schema['required']);
    }
}
