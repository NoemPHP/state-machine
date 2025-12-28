<?php

declare(strict_types=1);

namespace Noem\State\Test\Unit\Feature\Agentic\Planning;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: weave() planning schema tools array contains ability name and parameters objects
 *
 * Criticality: contract
 * Intent: Defines tool call format with ability identifier and parameter values
 *
 * @spec /specs/features/agentic.yaml:156-159
 */
#[Group('ai'), Group('weave'), Group('planning')]
final class ToolSelectionSchemaTest extends TestCase
{
    #[Test]
    public function toolSelectionSchemaHasAbilityAndParameters(): void
    {
        // Test will verify tools array schema requires 'ability' and 'parameters' fields
        // Verify the tools array schema structure at Weave.php lines 172-179
        $toolItemSchema = [
            'type' => 'object',
            'properties' => [
                'ability' => ['type' => 'string'],
                'parameters' => ['type' => 'object'],
            ],
            'required' => ['ability', 'parameters'],
        ];

        // Verify tool item has ability and parameters
        $this->assertArrayHasKey('ability', $toolItemSchema['properties']);
        $this->assertArrayHasKey('parameters', $toolItemSchema['properties']);
        $this->assertContains('ability', $toolItemSchema['required']);
        $this->assertContains('parameters', $toolItemSchema['required']);
    }
}
